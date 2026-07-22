<?php

declare(strict_types=1);

namespace Drupal\theater_tickets;

/**
 * Value object describing the outcome of a purchase attempt.
 *
 * Purchases are all-or-nothing: either every requested hold is converted
 * into a Ticket, or none are and the whole attempt is rolled back.
 */
final class PurchaseResult {

  /**
   * @param array<int> $ticketIds
   * @param array<int, string> $failures
   *   Map of hold ID to failure reason ('hold_not_found', 'not_owner',
   *   'expired', 'already_ticketed', 'unexpected_error').
   */
  private function __construct(
    public readonly bool $success,
    public readonly array $ticketIds,
    public readonly array $failures,
  ) {}

  /**
   * Builds a successful result where every hold became a ticket.
   *
   * @param array<int> $ticketIds
   */
  public static function success(array $ticketIds): self {
    return new self(TRUE, $ticketIds, []);
  }

  /**
   * Builds a failure result; nothing was purchased.
   *
   * @param array<int, string> $failures
   */
  public static function failure(array $failures): self {
    return new self(FALSE, [], $failures);
  }

}
