<?php

declare(strict_types=1);

namespace Drupal\theater_tickets;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Psr\Log\LoggerInterface;

/**
 * Wandelt gültige Seat Holds in permanente Tickets um (alles oder nichts).
 */
final class PurchaseService implements PurchaseServiceInterface {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function purchase(array $holdIds, AccountInterface $account): PurchaseResult {
    if (empty($holdIds)) {
      return PurchaseResult::failure(['_empty' => 'no_holds_given']);
    }

    $ticketStorage = $this->entityTypeManager->getStorage('theater_ticket');
    $now = $this->time->getRequestTime();
    $uid = (int) $account->id();

    $transaction = $this->database->startTransaction();

    try {
      // Erste Runde: jeden Hold final validieren, bevor irgendetwas
      // geschrieben wird – alles oder nichts.
      $holds = [];
      $failures = [];

      foreach ($holdIds as $holdId) {
        $hold = $this->database->select('theater_seat_hold', 'h')
          ->fields('h')
          ->condition('id', $holdId)
          ->execute()
          ->fetchAssoc();

        if (!$hold) {
          $failures[$holdId] = 'hold_not_found';
          continue;
        }
        if ((int) $hold['uid'] !== $uid) {
          $failures[$holdId] = 'not_owner';
          continue;
        }
        if ((int) $hold['expires'] <= $now) {
          $failures[$holdId] = 'expired';
          continue;
        }

        $existing = $ticketStorage->getQuery()
          ->condition('seat', $hold['seat_id'])
          ->condition('performance', $hold['performance_id'])
          ->accessCheck(FALSE)
          ->count()
          ->execute();
        if ($existing > 0) {
          $failures[$holdId] = 'already_ticketed';
          continue;
        }

        $holds[$holdId] = $hold;
      }

      if (!empty($failures)) {
        // Alles oder nichts: sobald ein Hold ungültig ist, wird nichts gebucht.
        $transaction->rollBack();
        return PurchaseResult::failure($failures);
      }

      $ticketIds = [];
      foreach ($holds as $holdId => $hold) {
        $ticket = $ticketStorage->create([
          'seat' => $hold['seat_id'],
          'performance' => $hold['performance_id'],
          'uid' => $hold['uid'],
          'created' => $now,
        ]);
        $ticket->save();
        $ticketIds[] = (int) $ticket->id();

        $this->database->delete('theater_seat_hold')
          ->condition('id', $holdId)
          ->execute();
      }

      unset($transaction);
      return PurchaseResult::success($ticketIds);
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      $this->logger->error('Kaufvorgang fehlgeschlagen: @message', ['@message' => $e->getMessage()]);
      return PurchaseResult::failure(['_exception' => 'unexpected_error']);
    }
  }

}
