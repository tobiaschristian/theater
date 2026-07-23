<?php

declare(strict_types=1);

namespace Drupal\theater_tickets;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\theater_tickets\Entity\PlatzInterface;
use Psr\Log\LoggerInterface;

/**
 * Verwaltet temporäre Sitzplatzreservierungen (Seat Holds).
 */
final class SeatHoldManager implements SeatHoldManagerInterface {

  /**
   * Gültigkeitsdauer einer Reservierung in Sekunden (5 Minuten).
   */
  private const HOLD_DURATION = 300;

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly TicketQuotaServiceInterface $quotaService,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function createHold(PlatzInterface $seat, NodeInterface $performance, AccountInterface $account): SeatHoldResult {
    if ($account->isAnonymous()) {
      return SeatHoldResult::failure('login_required');
    }

    if ($this->isSold($seat, $performance)) {
      return SeatHoldResult::failure('seat_sold');
    }

    if (!$this->quotaService->canReserve($account, $performance)) {
      return SeatHoldResult::failure('quota_exceeded');
    }

    $seatId = (int) $seat->id();
    $performanceId = (int) $performance->id();
    $uid = (int) $account->id();
    $now = $this->time->getRequestTime();

    $transaction = $this->database->startTransaction();

    try {
      // Eine abgelaufene Zeile für genau diesen Platz löschen, damit der
      // UNIQUE-Constraint nicht fälschlich einen längst ungültigen Hold
      // schützt. Läuft ein paralleler Request dieselbe Prozedur, gewinnt
      // entweder dieser hier (Insert erfolgreich) oder der andere (dessen
      // Insert zuerst committet, dieser hier scheitert an der Unique-Verletzung).
      $this->database->delete('theater_seat_hold')
        ->condition('seat_id', $seatId)
        ->condition('performance_id', $performanceId)
        ->condition('expires', $now, '<')
        ->execute();

      $id = $this->database->insert('theater_seat_hold')
        ->fields([
          'seat_id' => $seatId,
          'performance_id' => $performanceId,
          'uid' => $uid,
          'created' => $now,
          'expires' => $now + self::HOLD_DURATION,
        ])
        ->execute();

      unset($transaction);
      return SeatHoldResult::success((int) $id);
    }
    catch (IntegrityConstraintViolationException) {
      $transaction->rollBack();
      return SeatHoldResult::failure('seat_taken');
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      $this->logger->error('Sitzplatzreservierung fehlgeschlagen: @message', ['@message' => $e->getMessage()]);
      return SeatHoldResult::failure('unexpected_error');
    }
  }

  /**
   * Prüft, ob für diesen Platz in dieser Vorstellung bereits ein Ticket existiert.
   *
   * Nötig, weil der Seat-Hold beim Kauf gelöscht wird (siehe PurchaseService)
   * – ohne diese Prüfung könnte ein bereits verkaufter Platz sonst erneut
   * reserviert werden.
   */
  private function isSold(PlatzInterface $seat, NodeInterface $performance): bool {
    $count = $this->entityTypeManager->getStorage('theater_ticket')->getQuery()
      ->condition('seat', $seat->id())
      ->condition('performance', $performance->id())
      ->accessCheck(FALSE)
      ->count()
      ->execute();

    return $count > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function releaseHold(int $holdId, AccountInterface $account): bool {
    $hold = $this->database->select('theater_seat_hold', 'h')
      ->fields('h', ['uid'])
      ->condition('id', $holdId)
      ->execute()
      ->fetchAssoc();

    if (!$hold) {
      return FALSE;
    }

    $isOwner = (int) $hold['uid'] === (int) $account->id();
    if (!$isOwner && !$account->hasPermission('administer theater_tickets')) {
      return FALSE;
    }

    $deleted = $this->database->delete('theater_seat_hold')
      ->condition('id', $holdId)
      ->execute();

    return $deleted > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function isHeld(PlatzInterface $seat, NodeInterface $performance): bool {
    return (bool) $this->database->select('theater_seat_hold', 'h')
      ->condition('seat_id', (int) $seat->id())
      ->condition('performance_id', (int) $performance->id())
      ->condition('expires', $this->time->getRequestTime(), '>')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveHoldsForPerformance(NodeInterface $performance): array {
    $query = $this->database->select('theater_seat_hold', 'h')
      ->fields('h')
      ->condition('performance_id', (int) $performance->id())
      ->condition('expires', $this->time->getRequestTime(), '>');

    return $query->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveHoldsForUser(AccountInterface $account, ?NodeInterface $performance = NULL): array {
    $query = $this->database->select('theater_seat_hold', 'h')
      ->fields('h')
      ->condition('uid', (int) $account->id())
      ->condition('expires', $this->time->getRequestTime(), '>');

    if ($performance !== NULL) {
      $query->condition('performance_id', (int) $performance->id());
    }

    return $query->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollectExpiredHolds(): int {
    return (int) $this->database->delete('theater_seat_hold')
      ->condition('expires', $this->time->getRequestTime(), '<')
      ->execute();
  }

}
