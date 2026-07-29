<?php

declare(strict_types=1);

namespace Drupal\theater_newsletter\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\theater_newsletter\Entity\NewsletterSubscriberInterface;

/**
 * Bestätigt eine ausstehende Anmeldung manuell, ohne Bestätigungslink.
 *
 * Für den Fall, dass jemand die Bestätigungsmail nicht erhält (z. B.
 * Zustellprobleme) und der Admin die Anmeldung trotzdem freigeben möchte.
 */
final class SubscriberApproveForm extends ConfirmFormBase {

  private ?NewsletterSubscriberInterface $subscriber = NULL;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'theater_newsletter_subscriber_approve_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): \Stringable {
    return $this->t('Anmeldung für %email manuell freigeben?', [
      '%email' => $this->subscriber?->getEmail() ?? '',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('entity.newsletter_subscriber.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ContentEntityInterface $newsletter_subscriber = NULL): array {
    if (!$newsletter_subscriber instanceof NewsletterSubscriberInterface) {
      throw new \InvalidArgumentException('Ungültiger Abonnent.');
    }
    $this->subscriber = $newsletter_subscriber;

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($this->subscriber instanceof NewsletterSubscriberInterface && !$this->subscriber->isConfirmed()) {
      $this->subscriber->setStatus(NewsletterSubscriberInterface::STATUS_CONFIRMED);
      $this->subscriber->setConfirmedTime(\Drupal::time()->getRequestTime());
      $this->subscriber->save();
      $this->messenger()->addStatus($this->t('Anmeldung für %email wurde freigegeben.', ['%email' => $this->subscriber->getEmail()]));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
