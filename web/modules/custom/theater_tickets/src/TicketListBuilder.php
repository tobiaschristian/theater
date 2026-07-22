<?php

declare(strict_types=1);

namespace Drupal\theater_tickets;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Listet Tickets im Admin-Bereich auf.
 */
final class TicketListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Ticket');
    $header['performance'] = $this->t('Vorstellung');
    $header['uid'] = $this->t('Käufer');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\theater_tickets\Entity\TicketInterface $entity */
    $row['label'] = $entity->label();
    $row['performance'] = $entity->getPerformanceId();
    $row['uid'] = $entity->getOwnerUserId();
    return $row + parent::buildRow($entity);
  }

}
