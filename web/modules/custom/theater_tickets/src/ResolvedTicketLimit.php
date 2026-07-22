<?php

declare(strict_types=1);

namespace Drupal\theater_tickets;

use Drupal\theater_tickets\Entity\TicketLimitTierInterface;

/**
 * Value object: das für einen Nutzer aufgelöste Kauflimit.
 *
 * Getrennt von der TicketLimitTier-Config-Entity, damit ein per-User
 * Override (nur die Zahl, nicht der Modus) abgebildet werden kann, ohne
 * eine Config-Entity-Instanz im Speicher zu verändern.
 */
final class ResolvedTicketLimit {

  public function __construct(
    public readonly string $mode,
    public readonly int $limit,
  ) {}

  /**
   * Baut das Limit aus einer Kauflimit-Stufen-Config-Entity.
   */
  public static function fromTier(TicketLimitTierInterface $tier): self {
    return new self($tier->getMode(), $tier->getLimit());
  }

  public function isUnlimited(): bool {
    return $this->mode === TicketLimitTierInterface::MODE_UNLIMITED;
  }

  public function isPerSeason(): bool {
    return $this->mode === TicketLimitTierInterface::MODE_PER_SEASON;
  }

}
