<?php

declare(strict_types=1);

namespace Drupal\theater_tickets\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Definiert eine Kauflimit-Stufe.
 *
 * Die Entity-ID entspricht in der Regel einem Rollen-Maschinennamen
 * (z. B. "mitglied", "foerdermitglied") oder "default" für Nutzer ohne
 * passende Rolle. So kann eine Stufe pro Saison ohne Codeänderung
 * angepasst werden.
 *
 * @ConfigEntityType(
 *   id = "theater_ticket_limit_tier",
 *   label = @Translation("Kauflimit-Stufe"),
 *   label_collection = @Translation("Kauflimit-Stufen"),
 *   handlers = {
 *     "list_builder" = "Drupal\theater_tickets\TicketLimitTierListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "form" = {
 *       "add" = "Drupal\theater_tickets\Form\TicketLimitTierForm",
 *       "edit" = "Drupal\theater_tickets\Form\TicketLimitTierForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   admin_permission = "administer theater_tickets",
 *   config_prefix = "theater_ticket_limit_tier",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/theater-tickets/limit-tiers",
 *     "add-form" = "/admin/theater-tickets/limit-tiers/add",
 *     "edit-form" = "/admin/theater-tickets/limit-tiers/{theater_ticket_limit_tier}/edit",
 *     "delete-form" = "/admin/theater-tickets/limit-tiers/{theater_ticket_limit_tier}/delete",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "mode",
 *     "limit",
 *   },
 * )
 */
final class TicketLimitTier extends ConfigEntityBase implements TicketLimitTierInterface {

  /**
   * Rollen-Maschinenname oder "default".
   */
  protected string $id;

  /**
   * Anzeigename.
   */
  protected string $label;

  /**
   * Zählmodus: per_performance, per_season oder unlimited.
   */
  protected string $mode = self::MODE_PER_PERFORMANCE;

  /**
   * Maximale Anzahl (ignoriert bei unlimited).
   */
  protected int $limit = 0;

  /**
   * {@inheritdoc}
   */
  public function getMode(): string {
    return $this->mode;
  }

  /**
   * {@inheritdoc}
   */
  public function getLimit(): int {
    return $this->limit;
  }

}
