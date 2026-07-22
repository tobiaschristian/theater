<?php

declare(strict_types=1);

namespace Drupal\theater_tickets\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Definiert den Saal-Config-Entity-Typ.
 *
 * Ein Saal wird selten angelegt oder geändert (wenige Räume) und dient als
 * Stammdatum, auf das Vorstellungen und Plätze verweisen. Das eigentliche
 * Platzinventar lebt in Platz-Entities, nicht in $rows/$seatsPerRow hier –
 * diese beiden Werte sind reine Vorbelegungen für das Generierungsformular.
 *
 * @ConfigEntityType(
 *   id = "saal",
 *   label = @Translation("Saal"),
 *   label_collection = @Translation("Säle"),
 *   handlers = {
 *     "list_builder" = "Drupal\theater_tickets\SaalListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "form" = {
 *       "add" = "Drupal\theater_tickets\Form\SaalForm",
 *       "edit" = "Drupal\theater_tickets\Form\SaalForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   admin_permission = "administer theater_tickets",
 *   config_prefix = "saal",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/theater-tickets/saal",
 *     "add-form" = "/admin/theater-tickets/saal/add",
 *     "edit-form" = "/admin/theater-tickets/saal/{saal}/edit",
 *     "delete-form" = "/admin/theater-tickets/saal/{saal}/delete",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "rows",
 *     "seats_per_row",
 *   },
 * )
 */
final class Saal extends ConfigEntityBase implements SaalInterface {

  /**
   * Der Maschinenname des Saals.
   */
  protected string $id;

  /**
   * Der Anzeigename des Saals.
   */
  protected string $label;

  /**
   * Freitext-Beschreibung.
   */
  protected string $description = '';

  /**
   * Zuletzt verwendete Anzahl Reihen (Vorbelegung für das Generierungsformular).
   */
  protected int $rows = 0;

  /**
   * Zuletzt verwendete Anzahl Plätze pro Reihe (Vorbelegung für das Formular).
   */
  protected int $seats_per_row = 0;

}
