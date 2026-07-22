<?php

declare(strict_types=1);

namespace Drupal\theater_tickets;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Listet Plätze im Admin-Bereich auf.
 */
final class PlatzListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Platz');
    $header['saal'] = $this->t('Saal');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\theater_tickets\Entity\PlatzInterface $entity */
    $row['label'] = $entity->getDisplayLabel();
    $row['saal'] = $entity->getSaalId();
    return $row + parent::buildRow($entity);
  }

}
