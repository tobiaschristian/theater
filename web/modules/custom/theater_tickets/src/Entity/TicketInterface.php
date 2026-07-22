<?php

declare(strict_types=1);

namespace Drupal\theater_tickets\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Definiert das Interface für den Ticket-Content-Entity-Typ.
 */
interface TicketInterface extends ContentEntityInterface {

  /**
   * Gibt die referenzierte Platz-Entity-ID zurück.
   */
  public function getSeatId(): int;

  /**
   * Gibt die referenzierte Vorstellung-Node-ID zurück.
   */
  public function getPerformanceId(): int;

  /**
   * Gibt die User-ID des Käufers zurück.
   */
  public function getOwnerUserId(): int;

}
