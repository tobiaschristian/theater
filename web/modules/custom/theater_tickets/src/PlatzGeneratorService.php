<?php

declare(strict_types=1);

namespace Drupal\theater_tickets;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\theater_tickets\Entity\SaalInterface;

/**
 * Erzeugt Plätze für einen Saal in Massen (Reihen × Plätze pro Reihe).
 */
final class PlatzGeneratorService {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Erzeugt fehlende Plätze für einen Saal.
   *
   * Idempotent: bereits vorhandene (Saal, Reihe, Platznummer)-Kombinationen
   * werden übersprungen, damit ein erneuter Aufruf (z. B. um weitere
   * Reihen zu ergänzen) keine Duplikate anlegt.
   *
   * @return int
   *   Anzahl der neu erzeugten Plätze.
   */
  public function generate(SaalInterface $saal, int $rows, int $seatsPerRow): int {
    $storage = $this->entityTypeManager->getStorage('theater_platz');
    $created = 0;

    for ($row = 1; $row <= $rows; $row++) {
      $rowLabel = $this->rowLabelFromIndex($row);

      for ($seatNumber = 1; $seatNumber <= $seatsPerRow; $seatNumber++) {
        $existing = $storage->getQuery()
          ->condition('saal', $saal->id())
          ->condition('row_label', $rowLabel)
          ->condition('seat_number', $seatNumber)
          ->accessCheck(FALSE)
          ->count()
          ->execute();

        if ($existing > 0) {
          continue;
        }

        $storage->create([
          'saal' => $saal->id(),
          'row_label' => $rowLabel,
          'seat_number' => $seatNumber,
        ])->save();
        $created++;
      }
    }

    return $created;
  }

  /**
   * Wandelt einen 1-basierten Reihenindex in eine Buchstabenbezeichnung um
   * (1 => "A", 26 => "Z", 27 => "AA", ...).
   */
  private function rowLabelFromIndex(int $index): string {
    $label = '';
    while ($index > 0) {
      $index--;
      $label = chr(65 + ($index % 26)) . $label;
      $index = intdiv($index, 26);
    }
    return $label;
  }

}
