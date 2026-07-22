<?php

declare(strict_types=1);

namespace Drupal\theater_tickets\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Definiert das Interface für den Kauflimit-Stufen-Config-Entity-Typ.
 */
interface TicketLimitTierInterface extends ConfigEntityInterface {

  public const MODE_PER_PERFORMANCE = 'per_performance';
  public const MODE_PER_SEASON = 'per_season';
  public const MODE_UNLIMITED = 'unlimited';

  /**
   * Gibt den Zählmodus zurück.
   */
  public function getMode(): string;

  /**
   * Gibt das Limit zurück (irrelevant bei MODE_UNLIMITED).
   */
  public function getLimit(): int;

}
