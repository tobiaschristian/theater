<?php

declare(strict_types=1);

namespace Drupal\theater_newsletter;

use Drupal\theater_newsletter\Entity\NewsletterSubscriberInterface;
use Drupal\user\UserInterface;

/**
 * Kapselt die Anmelde-/Bestätigungs-/Abmelde-Logik des Newsletters.
 */
interface TokenManagerInterface {

  /**
   * Normalisiert eine E-Mail-Adresse (trim + lowercase) für Vergleiche.
   */
  public function normalizeEmail(string $email): string;

  /**
   * Lädt den Abonnenten-Datensatz zu einer E-Mail-Adresse, falls vorhanden.
   */
  public function loadSubscriberByEmail(string $email): ?NewsletterSubscriberInterface;

  /**
   * Prüft (ohne Statusänderung), ob Token und ID zusammenpassen.
   *
   * Für die Formularvalidierung gedacht, damit der eigentliche
   * Statuswechsel erst im Submit-Handler passiert.
   */
  public function findValidSubscriber(int $subscriberId, string $token): ?NewsletterSubscriberInterface;

  /**
   * Verarbeitet eine Anmeldung über das öffentliche Formular.
   *
   * Legt bei Bedarf einen neuen Datensatz an oder reaktiviert einen
   * bestehenden (siehe Klassendokumentation TokenManager) und verschickt
   * die Bestätigungsmail. Wirft absichtlich keine unterschiedlichen
   * Rückgabewerte für "existiert bereits" vs. "neu angelegt", damit der
   * Formular-Handler keine E-Mail-Enumeration ermöglicht.
   */
  public function subscribe(string $email, string $ip, ?int $uid = NULL): void;

  /**
   * Bestätigt eine Anmeldung, wenn der Token zum Datensatz passt.
   */
  public function confirmWithToken(int $subscriberId, string $token): bool;

  /**
   * Meldet ab, wenn der Token zum Datensatz passt.
   */
  public function unsubscribeWithToken(int $subscriberId, string $token): bool;

  /**
   * Verknüpft einen unverknüpften Abonnenten-Datensatz mit dem Konto,
   * falls die E-Mail-Adresse übereinstimmt (Aufruf bei jedem Login).
   */
  public function syncAccountLink(UserInterface $account): void;

  /**
   * Gibt den mit dem Konto verknüpften Abonnenten-Datensatz zurück.
   */
  public function getSubscriberForAccount(UserInterface $account): ?NewsletterSubscriberInterface;

  /**
   * Setzt den Newsletter-Status für ein eingeloggtes Konto direkt
   * (ohne Double-Opt-in, da die Session selbst der Identitätsnachweis ist).
   */
  public function setSubscribedForAccount(UserInterface $account, bool $subscribed, string $ip): void;

}
