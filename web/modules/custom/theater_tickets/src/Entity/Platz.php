<?php

declare(strict_types=1);

namespace Drupal\theater_tickets\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Definiert die Platz-Entity (ein einzelner Sitzplatz in einem Saal).
 *
 * Pro Saal können hunderte Plätze existieren; sie werden per
 * PlatzGeneratorService in Massen erzeugt, nicht einzeln von Hand.
 *
 * @ContentEntityType(
 *   id = "theater_platz",
 *   label = @Translation("Platz"),
 *   label_collection = @Translation("Plätze"),
 *   handlers = {
 *     "list_builder" = "Drupal\theater_tickets\PlatzListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 *   base_table = "theater_platz",
 *   admin_permission = "administer theater_tickets",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/theater-tickets/platz",
 *   },
 * )
 */
final class Platz extends ContentEntityBase implements PlatzInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['saal'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Saal'))
      ->setSetting('target_type', 'saal')
      ->setRequired(TRUE);

    $fields['row_label'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Reihe'))
      ->setSetting('max_length', 8)
      ->setRequired(TRUE);

    $fields['seat_number'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Platznummer'))
      ->setRequired(TRUE);

    // Dokumentierter Erweiterungspunkt für spätere Preisstaffelung
    // (z. B. "Parkett"/"Loge"). In Phase 1 unbenutzt und nicht validiert.
    $fields['category'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Kategorie (reserviert für spätere Preisstaffelung)'))
      ->setSetting('max_length', 64)
      ->setRequired(FALSE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Erstellt'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getSaalId(): string {
    return (string) $this->get('saal')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getRowLabel(): string {
    return (string) $this->get('row_label')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getSeatNumber(): int {
    return (int) $this->get('seat_number')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayLabel(): string {
    return (string) new TranslatableMarkup('Reihe @row, Platz @number', [
      '@row' => $this->getRowLabel(),
      '@number' => $this->getSeatNumber(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return $this->getDisplayLabel();
  }

}
