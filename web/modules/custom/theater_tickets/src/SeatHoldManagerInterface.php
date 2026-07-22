<?php

declare(strict_types=1);

namespace Drupal\theater_tickets;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\theater_tickets\Entity\PlatzInterface;

/**
 * Verwaltet temporäre Sitzplatzreservierungen (Seat Holds).
 */
interface SeatHoldManagerInterface {

  /**
   * Versucht, einen Platz für eine Vorstellung zu reservieren.
   *
   * Prüft zuerst das Kauflimit, löscht dann eine evtl. abgelaufene
   * Reservierung für denselben Platz und legt die neue Reservierung an –
   * alles innerhalb einer Transaktion. Ein DB-UNIQUE-Constraint auf
   * (seat_id, performance_id) verhindert doppelte gültige Reservierungen
   * bei gleichzeitigem Zugriff.
   */
  public function createHold(PlatzInterface $seat, NodeInterface $performance, AccountInterface $account): SeatHoldResult;

  /**
   * Gibt eine Reservierung frei.
   *
   * Nur der Eigentümer oder ein Nutzer mit der Berechtigung
   * "administer theater_tickets" darf freigeben.
   */
  public function releaseHold(int $holdId, AccountInterface $account): bool;

  /**
   * Prüft live (gegen die aktuelle Zeit), ob ein Platz aktuell gehalten wird.
   */
  public function isHeld(PlatzInterface $seat, NodeInterface $performance): bool;

  /**
   * Gibt alle aktiven Reservierungen für eine Vorstellung zurück.
   *
   * @return array<int, array{id: int, seat_id: int, performance_id: int, uid: int, created: int, expires: int}>
   */
  public function getActiveHoldsForPerformance(NodeInterface $performance): array;

  /**
   * Gibt alle aktiven Reservierungen eines Nutzers zurück.
   *
   * @return array<int, array{id: int, seat_id: int, performance_id: int, uid: int, created: int, expires: int}>
   */
  public function getActiveHoldsForUser(AccountInterface $account, ?NodeInterface $performance = NULL): array;

  /**
   * Löscht abgelaufene Reservierungen (aufgerufen aus hook_cron()).
   *
   * @return int
   *   Anzahl der gelöschten Zeilen.
   */
  public function garbageCollectExpiredHolds(): int;

}
