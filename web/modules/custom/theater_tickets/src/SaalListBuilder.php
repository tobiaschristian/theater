<?php

declare(strict_types=1);

namespace Drupal\theater_tickets;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Listet die konfigurierten Säle im Admin-Bereich auf.
 */
final class SaalListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Saal');
    $header['description'] = $this->t('Beschreibung');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\theater_tickets\Entity\SaalInterface $entity */
    $row['label'] = $entity->label();
    $row['description'] = $entity->get('description');
    return $row + parent::buildRow($entity);
  }

}
