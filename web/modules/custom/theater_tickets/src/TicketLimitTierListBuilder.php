<?php

declare(strict_types=1);

namespace Drupal\theater_tickets;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\theater_tickets\Entity\TicketLimitTierInterface;

/**
 * Listet die Kauflimit-Stufen im Admin-Bereich auf.
 */
final class TicketLimitTierListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Stufe');
    $header['mode'] = $this->t('Modus');
    $header['limit'] = $this->t('Limit');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\theater_tickets\Entity\TicketLimitTierInterface $entity */
    $modes = [
      TicketLimitTierInterface::MODE_PER_PERFORMANCE => $this->t('Pro Vorstellung'),
      TicketLimitTierInterface::MODE_PER_SEASON => $this->t('Pro Saison'),
      TicketLimitTierInterface::MODE_UNLIMITED => $this->t('Unbegrenzt'),
    ];

    $row['label'] = $entity->label();
    $row['mode'] = $modes[$entity->getMode()] ?? $entity->getMode();
    $row['limit'] = $entity->getMode() === TicketLimitTierInterface::MODE_UNLIMITED ? '—' : $entity->getLimit();
    return $row + parent::buildRow($entity);
  }

}
