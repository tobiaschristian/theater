<?php

declare(strict_types=1);

namespace Drupal\theater_members;

use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Verknüpft Benutzerkonten mit Mitglied-Nodes.
 */
interface MemberLookupInterface {

  /**
   * Lädt den Mitglied-Node, der über field_user_account verknüpft ist.
   */
  public function findByUserId(int $uid): ?NodeInterface;

  /**
   * Lädt einen (noch unverknüpften oder beliebigen) Mitglied-Node per E-Mail.
   */
  public function findByEmail(string $email): ?NodeInterface;

  /**
   * Findet den verknüpften Mitglied-Node oder legt einen an.
   *
   * Suchreihenfolge: erst per verknüpftem Konto (uid), dann per
   * E-Mail-Abgleich (verknüpft einen gefundenen, noch nicht verknüpften
   * Node statt einen Duplikat anzulegen), sonst Neuanlage mit
   * Vorname/Nachname/E-Mail vom Konto, Eintrittsdatum = heute,
   * Mitgliedschaftsart = "mitglied".
   *
   * Speichert den Node NICHT automatisch, falls der Aufrufer noch weitere
   * Felder setzen möchte, bevor gespeichert wird — außer im
   * Verknüpfungsfall (dort wird sofort gespeichert, damit die
   * uid-Verknüpfung nicht verloren geht, falls der Aufrufer den Node aus
   * irgendeinem Grund nicht selbst speichert).
   */
  public function getOrCreateForAccount(UserInterface $account): NodeInterface;

}
