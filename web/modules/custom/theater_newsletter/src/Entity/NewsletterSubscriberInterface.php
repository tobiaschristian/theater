<?php

declare(strict_types=1);

namespace Drupal\theater_newsletter\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Definiert das Interface für den Newsletter-Abonnent-Content-Entity-Typ.
 */
interface NewsletterSubscriberInterface extends ContentEntityInterface {

  public const STATUS_PENDING = 'pending';
  public const STATUS_CONFIRMED = 'confirmed';
  public const STATUS_UNSUBSCRIBED = 'unsubscribed';

  /**
   * Gibt die (normalisierte) E-Mail-Adresse zurück.
   */
  public function getEmail(): string;

  /**
   * Setzt die E-Mail-Adresse.
   */
  public function setEmail(string $email): static;

  /**
   * Gibt die User-ID des verknüpften Kontos zurück, falls vorhanden.
   */
  public function getUserId(): ?int;

  /**
   * Verknüpft (oder entfernt) das Konto.
   */
  public function setUserId(?int $uid): static;

  /**
   * Gibt den Status zurück (pending/confirmed/unsubscribed).
   */
  public function getStatus(): string;

  /**
   * Setzt den Status.
   */
  public function setStatus(string $status): static;

  public function isPending(): bool;

  public function isConfirmed(): bool;

  public function isUnsubscribed(): bool;

  /**
   * Gibt den gespeicherten Hash des Bestätigungs-/Abmelde-Tokens zurück.
   */
  public function getTokenHash(): string;

  /**
   * Setzt den Hash des Bestätigungs-/Abmelde-Tokens.
   */
  public function setTokenHash(string $tokenHash): static;

  /**
   * Prüft, ob ein Klartext-Token zum gespeicherten Hash passt.
   */
  public function matchesToken(string $token): bool;

  public function getConfirmedTime(): ?int;

  public function setConfirmedTime(?int $timestamp): static;

  public function getUnsubscribedTime(): ?int;

  public function setUnsubscribedTime(?int $timestamp): static;

  /**
   * Gibt die IP-Adresse zum Zeitpunkt der Einwilligung zurück (DSGVO-Nachweis).
   */
  public function getConsentIp(): string;

  public function setConsentIp(string $ip): static;

}
