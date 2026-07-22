<?php

declare(strict_types=1);

namespace Drupal\theater_tickets\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Definiert das Interface für den Platz-Content-Entity-Typ.
 */
interface PlatzInterface extends ContentEntityInterface {

  /**
   * Gibt die referenzierte Saal-Entity-ID zurück.
   */
  public function getSaalId(): string;

  /**
   * Gibt die Reihenbezeichnung zurück (z. B. "A").
   */
  public function getRowLabel(): string;

  /**
   * Gibt die Platznummer innerhalb der Reihe zurück.
   */
  public function getSeatNumber(): int;

  /**
   * Gibt eine für Menschen lesbare Bezeichnung zurück ("Reihe A, Platz 12").
   */
  public function getDisplayLabel(): string;

}
