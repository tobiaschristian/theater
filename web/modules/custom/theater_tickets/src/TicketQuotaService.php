<?php

declare(strict_types=1);

namespace Drupal\theater_tickets;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\theater_tickets\Entity\TicketLimitTier;
use Drupal\theater_tickets\Entity\TicketLimitTierInterface;
use Drupal\user\UserInterface;

/**
 * Ermittelt und prüft Kauflimits pro Nutzer.
 */
final class TicketQuotaService implements TicketQuotaServiceInterface {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function resolveLimit(AccountInterface $account): ResolvedTicketLimit {
    $baseTier = $this->getRoleTier($account);

    $override = $this->getUserOverrideLimit($account);
    if ($override !== NULL) {
      return new ResolvedTicketLimit($baseTier->getMode(), $override);
    }

    return ResolvedTicketLimit::fromTier($baseTier);
  }

  /**
   * Ermittelt die passende Kauflimit-Stufe anhand der Rollen des Nutzers.
   *
   * Priorität: foerdermitglied vor mitglied (analog zu
   * theater_membership_get_current_membership_type()), sonst "default".
   */
  private function getRoleTier(AccountInterface $account): TicketLimitTierInterface {
    $storage = $this->entityTypeManager->getStorage('theater_ticket_limit_tier');

    foreach (['foerdermitglied', 'mitglied'] as $role) {
      if ($account->hasRole($role)) {
        $tier = $storage->load($role);
        if ($tier instanceof TicketLimitTierInterface) {
          return $tier;
        }
      }
    }

    $default = $storage->load('default');
    if ($default instanceof TicketLimitTierInterface) {
      return $default;
    }

    // Kein "default"-Tier konfiguriert: sicherer Fallback ohne Limit-Umgehung
    // (Limit 0, statt versehentlich unbegrenzt zu erlauben).
    return new TicketLimitTier([
      'id' => 'fallback',
      'label' => 'Fallback',
      'mode' => TicketLimitTierInterface::MODE_PER_PERFORMANCE,
      'limit' => 0,
    ], 'theater_ticket_limit_tier');
  }

  /**
   * Liest das optionale per-User-Override-Limit, falls gesetzt.
   */
  private function getUserOverrideLimit(AccountInterface $account): ?int {
    if ($account->isAnonymous()) {
      return NULL;
    }

    $user = $this->entityTypeManager->getStorage('user')->load($account->id());
    if (!$user instanceof UserInterface || !$user->hasField('field_ticket_limit_override')) {
      return NULL;
    }

    $value = $user->get('field_ticket_limit_override')->value;
    return $value === NULL || $value === '' ? NULL : (int) $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentUsage(AccountInterface $account, NodeInterface $performance): int {
    $limit = $this->resolveLimit($account);
    $performanceIds = $limit->isPerSeason()
      ? $this->getPerformanceIdsInSameSeason($performance)
      : [(int) $performance->id()];

    return $this->countActiveHolds($account, $performanceIds) + $this->countTickets($account, $performanceIds);
  }

  /**
   * {@inheritdoc}
   */
  public function canReserve(AccountInterface $account, NodeInterface $performance, int $additional = 1): bool {
    $limit = $this->resolveLimit($account);
    if ($limit->isUnlimited()) {
      return TRUE;
    }

    return $this->getCurrentUsage($account, $performance) + $additional <= $limit->limit;
  }

  /**
   * Gibt alle Vorstellung-Node-IDs mit derselben Saison wie $performance zurück.
   *
   * @return array<int>
   */
  private function getPerformanceIdsInSameSeason(NodeInterface $performance): array {
    if (!$performance->hasField('field_saison') || $performance->get('field_saison')->isEmpty()) {
      return [(int) $performance->id()];
    }

    $seasonTermId = $performance->get('field_saison')->target_id;

    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'vorstellung')
      ->condition('field_saison', $seasonTermId)
      ->accessCheck(FALSE)
      ->execute();

    return array_map('intval', array_values($ids));
  }

  /**
   * Zählt aktive (nicht abgelaufene) Seat Holds des Nutzers.
   *
   * @param array<int> $performanceIds
   */
  private function countActiveHolds(AccountInterface $account, array $performanceIds): int {
    if (empty($performanceIds)) {
      return 0;
    }

    return (int) $this->database->select('theater_seat_hold', 'h')
      ->condition('uid', (int) $account->id())
      ->condition('performance_id', $performanceIds, 'IN')
      ->condition('expires', $this->time->getRequestTime(), '>')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Zählt bereits ausgestellte Tickets des Nutzers.
   *
   * @param array<int> $performanceIds
   */
  private function countTickets(AccountInterface $account, array $performanceIds): int {
    if (empty($performanceIds)) {
      return 0;
    }

    return (int) $this->entityTypeManager->getStorage('theater_ticket')->getQuery()
      ->condition('uid', (int) $account->id())
      ->condition('performance', $performanceIds, 'IN')
      ->accessCheck(FALSE)
      ->count()
      ->execute();
  }

}
