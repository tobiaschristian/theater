<?php

declare(strict_types=1);

namespace Drupal\theater_tickets\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Definiert die Ticket-Entity (ein endgültig zugewiesener/gekaufter Platz).
 *
 * Ein Ticket entsteht, wenn PurchaseService einen gültigen Seat Hold in
 * einen permanenten Datensatz umwandelt. Das Feld "commerce_order_item"
 * bleibt in Phase 1 leer und ist der vorgesehene Erweiterungspunkt für die
 * spätere Drupal-Commerce-Anbindung (Phase 2) – kein Schema-Umbau nötig.
 *
 * @ContentEntityType(
 *   id = "theater_ticket",
 *   label = @Translation("Ticket"),
 *   label_collection = @Translation("Tickets"),
 *   handlers = {
 *     "list_builder" = "Drupal\theater_tickets\TicketListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 *   base_table = "theater_ticket",
 *   admin_permission = "administer theater_tickets",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/theater-tickets/ticket",
 *   },
 * )
 */
final class Ticket extends ContentEntityBase implements TicketInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['seat'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Platz'))
      ->setSetting('target_type', 'theater_platz')
      ->setRequired(TRUE);

    $fields['performance'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Vorstellung'))
      ->setSetting('target_type', 'node')
      ->setSetting('handler_settings', ['target_bundles' => ['vorstellung' => 'vorstellung']])
      ->setRequired(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Käufer'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Gekauft am'));

    // Phase-2-Erweiterungspunkt: bleibt in Phase 1 unbenutzt/leer.
    $fields['commerce_order_item'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Commerce-Bestellposition (Phase 2)'))
      ->setRequired(FALSE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getSeatId(): int {
    return (int) $this->get('seat')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getPerformanceId(): int {
    return (int) $this->get('performance')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerUserId(): int {
    return (int) $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return (string) new TranslatableMarkup('Ticket #@id', ['@id' => $this->id()]);
  }

}
