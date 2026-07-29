<?php

declare(strict_types=1);

namespace Drupal\theater_newsletter\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Definiert die Newsletter-Abonnent-Entity.
 *
 * Ein Datensatz entsteht bei jeder Anmeldung über das öffentliche
 * Anmeldeformular (anonym oder eingeloggt) und bleibt auch nach einer
 * Abmeldung bestehen (kein Hard-Delete), damit eine erneute Anmeldung den
 * bestehenden Datensatz reaktiviert statt Duplikate anzulegen. Der
 * gespeicherte "token_hash" ist der SHA-256-Hash des Bestätigungs-/
 * Abmelde-Tokens aus dem Mail-Link – der Klartext-Token selbst wird nie in
 * der Datenbank abgelegt.
 *
 * @ContentEntityType(
 *   id = "newsletter_subscriber",
 *   label = @Translation("Newsletter-Abonnent"),
 *   label_collection = @Translation("Newsletter-Abonnenten"),
 *   handlers = {
 *     "list_builder" = "Drupal\theater_newsletter\NewsletterSubscriberListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "storage_schema" = "Drupal\theater_newsletter\NewsletterSubscriberStorageSchema",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "form" = {
 *       "edit" = "Drupal\theater_newsletter\Form\SubscriberEditForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *   },
 *   base_table = "newsletter_subscriber",
 *   admin_permission = "administer theater_newsletter",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/theater-newsletter/abonnenten",
 *     "edit-form" = "/admin/theater-newsletter/abonnenten/{newsletter_subscriber}/edit",
 *     "delete-form" = "/admin/theater-newsletter/abonnenten/{newsletter_subscriber}/delete",
 *   },
 * )
 */
final class NewsletterSubscriber extends ContentEntityBase implements NewsletterSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['email'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('E-Mail-Adresse'))
      ->setSetting('max_length', 254)
      ->setRequired(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Verknüpftes Konto'))
      ->setSetting('target_type', 'user')
      ->setRequired(FALSE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Status'))
      ->setSetting('allowed_values', [
        NewsletterSubscriberInterface::STATUS_PENDING => new TranslatableMarkup('Ausstehend (Double-Opt-in)'),
        NewsletterSubscriberInterface::STATUS_CONFIRMED => new TranslatableMarkup('Bestätigt'),
        NewsletterSubscriberInterface::STATUS_UNSUBSCRIBED => new TranslatableMarkup('Abgemeldet'),
      ])
      ->setRequired(TRUE)
      ->setDefaultValue(NewsletterSubscriberInterface::STATUS_PENDING);

    // SHA-256-Hash (64 Hex-Zeichen) des Bestätigungs-/Abmelde-Tokens; der
    // Klartext-Token steht nur im versendeten Mail-Link.
    $fields['token_hash'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Token-Hash'))
      ->setSetting('max_length', 64)
      ->setRequired(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Angemeldet am'));

    $fields['confirmed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Bestätigt am'))
      ->setRequired(FALSE);

    $fields['unsubscribed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Abgemeldet am'))
      ->setRequired(FALSE);

    // DSGVO-Nachweis der Einwilligung (Double-Opt-in-Anmeldung).
    $fields['consent_ip'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('IP-Adresse bei Anmeldung'))
      ->setSetting('max_length', 45)
      ->setRequired(FALSE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail(): string {
    return (string) $this->get('email')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setEmail(string $email): static {
    $this->set('email', $email);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserId(): ?int {
    $target_id = $this->get('uid')->target_id;
    return $target_id !== NULL ? (int) $target_id : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setUserId(?int $uid): static {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): string {
    return (string) $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(string $status): static {
    $this->set('status', $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPending(): bool {
    return $this->getStatus() === NewsletterSubscriberInterface::STATUS_PENDING;
  }

  /**
   * {@inheritdoc}
   */
  public function isConfirmed(): bool {
    return $this->getStatus() === NewsletterSubscriberInterface::STATUS_CONFIRMED;
  }

  /**
   * {@inheritdoc}
   */
  public function isUnsubscribed(): bool {
    return $this->getStatus() === NewsletterSubscriberInterface::STATUS_UNSUBSCRIBED;
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenHash(): string {
    return (string) $this->get('token_hash')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTokenHash(string $tokenHash): static {
    $this->set('token_hash', $tokenHash);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function matchesToken(string $token): bool {
    $stored = $this->getTokenHash();
    if ($stored === '') {
      return FALSE;
    }
    return hash_equals($stored, hash('sha256', $token));
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmedTime(): ?int {
    $value = $this->get('confirmed')->value;
    return $value !== NULL ? (int) $value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfirmedTime(?int $timestamp): static {
    $this->set('confirmed', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUnsubscribedTime(): ?int {
    $value = $this->get('unsubscribed')->value;
    return $value !== NULL ? (int) $value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setUnsubscribedTime(?int $timestamp): static {
    $this->set('unsubscribed', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConsentIp(): string {
    return (string) $this->get('consent_ip')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setConsentIp(string $ip): static {
    $this->set('consent_ip', $ip);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return $this->getEmail();
  }

}
