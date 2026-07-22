<?php

declare(strict_types=1);

namespace Drupal\theater_tickets;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Ermittelt und prüft Kauflimits pro Nutzer.
 */
interface TicketQuotaServiceInterface {

  /**
   * Löst das geltende Kauflimit für einen Nutzer auf.
   *
   * Reihenfolge: per-User-Override-Feld (nur Zahl) > Rolle
   * (foerdermitglied vor mitglied) > "default"-Stufe.
   */
  public function resolveLimit(AccountInterface $account): ResolvedTicketLimit;

  /**
   * Zählt aktive Reservierungen plus bereits ausgestellte Tickets.
   *
   * Im Modus "per_performance" nur für $performance, im Modus
   * "per_season" über alle Vorstellungen derselben Saison summiert.
   */
  public function getCurrentUsage(AccountInterface $account, NodeInterface $performance): int;

  /**
   * Prüft, ob der Nutzer $additional weitere Plätze reservieren/kaufen darf.
   */
  public function canReserve(AccountInterface $account, NodeInterface $performance, int $additional = 1): bool;

}
