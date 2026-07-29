<?php

declare(strict_types=1);

namespace Drupal\theater_newsletter\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\theater_newsletter\Entity\NewsletterSubscriberInterface;

/**
 * Admin-Formular zum manuellen Bearbeiten eines Newsletter-Abonnenten.
 *
 * Die Basis-Felder sind nicht für die generische Formularanzeige
 * konfiguriert (kein Field-UI-Overhead nötig für eine reine Admin-Tabelle),
 * daher werden E-Mail und Status hier explizit aufgebaut statt sich auf
 * ContentEntityForm::form() zu verlassen.
 */
final class SubscriberEditForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\theater_newsletter\Entity\NewsletterSubscriberInterface $entity */
    $entity = $this->entity;

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('E-Mail-Adresse'),
      '#default_value' => $entity->getEmail(),
      '#required' => TRUE,
    ];

    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => [
        NewsletterSubscriberInterface::STATUS_PENDING => $this->t('Ausstehend (Double-Opt-in)'),
        NewsletterSubscriberInterface::STATUS_CONFIRMED => $this->t('Bestätigt'),
        NewsletterSubscriberInterface::STATUS_UNSUBSCRIBED => $this->t('Abgemeldet'),
      ],
      '#default_value' => $entity->getStatus(),
      '#description' => $this->t('Auf "Bestätigt" setzen, um eine ausstehende Anmeldung ohne Bestätigungslink manuell freizugeben.'),
    ];

    $form['info'] = [
      '#type' => 'item',
      '#title' => $this->t('Weitere Angaben'),
      '#markup' => $this->t('Angemeldet am: @created<br>Konto: @uid<br>IP bei Anmeldung: @ip', [
        '@created' => $entity->get('created')->value ? \Drupal::service('date.formatter')->format((int) $entity->get('created')->value) : '–',
        '@uid' => $entity->getUserId() !== NULL ? '#' . $entity->getUserId() : '–',
        '@ip' => $entity->getConsentIp() !== '' ? $entity->getConsentIp() : '–',
      ]),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\theater_newsletter\Entity\NewsletterSubscriberInterface $entity */
    $entity = $this->entity;
    $newStatus = (string) $form_state->getValue('status');
    $wasConfirmed = $entity->isConfirmed();
    $wasUnsubscribed = $entity->isUnsubscribed();

    $entity->setEmail((string) $form_state->getValue('email'));
    $entity->setStatus($newStatus);

    if ($newStatus === NewsletterSubscriberInterface::STATUS_CONFIRMED && !$wasConfirmed) {
      $entity->setConfirmedTime(\Drupal::time()->getRequestTime());
    }
    if ($newStatus === NewsletterSubscriberInterface::STATUS_UNSUBSCRIBED && !$wasUnsubscribed) {
      $entity->setUnsubscribedTime(\Drupal::time()->getRequestTime());
    }

    $result = parent::save($form, $form_state);

    $this->messenger()->addStatus($this->t('Abonnent %email gespeichert.', ['%email' => $entity->getEmail()]));
    $form_state->setRedirectUrl($entity->toUrl('collection'));

    return $result;
  }

}
