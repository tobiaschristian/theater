<?php

declare(strict_types=1);

namespace Drupal\theater_tickets;

/**
 * Value object describing the outcome of a seat hold attempt.
 */
final class SeatHoldResult {

  private function __construct(
    public readonly bool $success,
    public readonly ?int $holdId,
    public readonly ?string $reason,
  ) {}

  /**
   * Builds a successful result.
   */
  public static function success(int $holdId): self {
    return new self(TRUE, $holdId, NULL);
  }

  /**
   * Builds a failure result.
   *
   * @param string $reason
   *   One of 'seat_taken', 'quota_exceeded', 'login_required', 'unexpected_error'.
   */
  public static function failure(string $reason): self {
    return new self(FALSE, NULL, $reason);
  }

}
