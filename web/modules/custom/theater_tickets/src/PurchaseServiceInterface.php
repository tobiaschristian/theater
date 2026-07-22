<?php

declare(strict_types=1);

namespace Drupal\theater_tickets;

use Drupal\Core\Session\AccountInterface;

/**
 * Wandelt gültige Seat Holds in permanente Tickets um.
 *
 * Phase-1-Platzhalter für den eigentlichen Zahlungsvorgang: Phase 2
 * (Drupal Commerce) ruft purchase() aus einem Order-Place-Event heraus auf
 * und ergänzt anschließend die Commerce-Bestellposition an den erzeugten
 * Tickets, ohne diesen Service anzupassen.
 */
interface PurchaseServiceInterface {

  /**
   * Kauft die angegebenen Reservierungen (alles oder nichts).
   *
   * Jeder Hold wird final erneut geprüft (existiert noch, gehört dem
   * Aufrufer, ist noch nicht abgelaufen, noch kein Ticket vorhanden).
   * Ist auch nur einer davon ungültig, wird der komplette Versuch
   * abgebrochen und nichts gebucht.
   *
   * @param array<int> $holdIds
   */
  public function purchase(array $holdIds, AccountInterface $account): PurchaseResult;

}
