<?php

declare(strict_types=1);

namespace Drupal\theater_newsletter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;
use Drupal\theater_newsletter\Entity\NewsletterSubscriberInterface;

/**
 * Listet Newsletter-Abonnenten im Admin-Bereich auf.
 */
final class NewsletterSubscriberListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['email'] = $this->t('E-Mail-Adresse');
    $header['status'] = $this->t('Status');
    $header['konto'] = $this->t('Konto');
    $header['angemeldet'] = $this->t('Angemeldet am');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\theater_newsletter\Entity\NewsletterSubscriberInterface $entity */
    $row['email'] = $entity->getEmail();
    $row['status'] = $this->formatStatus($entity);
    $row['konto'] = $entity->getUserId() !== NULL ? '#' . $entity->getUserId() : $this->t('—');
    $row['angemeldet'] = $entity->get('created')->value
      ? \Drupal::service('date.formatter')->format((int) $entity->get('created')->value, 'short')
      : '';
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   *
   * Ergänzt Edit/Delete (automatisch aus den Link-Templates der Entity)
   * um einen "Freigeben"-Link für ausstehende Anmeldungen.
   */
  public function getOperations(EntityInterface $entity): array {
    $operations = parent::getOperations($entity);

    /** @var \Drupal\theater_newsletter\Entity\NewsletterSubscriberInterface $entity */
    if ($entity->isPending() && $entity->access('update')) {
      $operations['approve'] = [
        'title' => $this->t('Freigeben'),
        'weight' => -10,
        'url' => Url::fromRoute('theater_newsletter.admin_approve', ['newsletter_subscriber' => $entity->id()]),
      ];
    }

    return $operations;
  }

  /**
   * Gibt eine menschenlesbare Statusbezeichnung zurück.
   */
  private function formatStatus(NewsletterSubscriberInterface $entity): string {
    return match ($entity->getStatus()) {
      NewsletterSubscriberInterface::STATUS_PENDING => (string) $this->t('Ausstehend'),
      NewsletterSubscriberInterface::STATUS_CONFIRMED => (string) $this->t('Bestätigt'),
      NewsletterSubscriberInterface::STATUS_UNSUBSCRIBED => (string) $this->t('Abgemeldet'),
      default => $entity->getStatus(),
    };
  }

}
