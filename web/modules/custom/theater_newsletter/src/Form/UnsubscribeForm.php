<?php

declare(strict_types=1);

namespace Drupal\theater_newsletter\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\theater_newsletter\TokenManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Abmeldeseite, per Token erreichbar – unabhängig vom Login-Status.
 *
 * Wie bei ConfirmForm ändert erst der Submit (POST) den Status, nicht
 * schon der bloße Aufruf des Links per GET.
 */
final class UnsubscribeForm extends FormBase {

  public function __construct(
    private readonly TokenManagerInterface $tokenManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('theater_newsletter.token_manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'theater_newsletter_unsubscribe_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?int $subscriber = NULL, ?string $token = NULL): array {
    $form_state->set('subscriber_id', $subscriber);
    $form_state->set('token', $token);

    $form['hinweis'] = [
      '#markup' => '<p>' . $this->t('Möchtest du dich wirklich vom Newsletter abmelden?') . '</p>',
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Ja, abmelden'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $subscriberId = $form_state->get('subscriber_id');
    $token = $form_state->get('token');

    if (!is_int($subscriberId) || !is_string($token) || $token === ''
      || !$this->tokenManager->findValidSubscriber($subscriberId, $token)) {
      $form_state->setErrorByName('', $this->t('Dieser Abmeldelink ist ungültig oder abgelaufen.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $subscriberId = $form_state->get('subscriber_id');
    $token = $form_state->get('token');

    if ($this->tokenManager->unsubscribeWithToken($subscriberId, $token)) {
      $this->messenger()->addStatus($this->t('Du wurdest vom Newsletter abgemeldet.'));
      return;
    }

    $this->messenger()->addError($this->t('Dieser Abmeldelink ist ungültig oder abgelaufen.'));
  }

}
