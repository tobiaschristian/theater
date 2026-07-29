<?php

declare(strict_types=1);

namespace Drupal\theater_newsletter\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\theater_newsletter\TokenManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Bestätigungsseite für die Newsletter-Anmeldung (Double-Opt-in).
 *
 * Der eigentliche Statuswechsel passiert erst beim Formular-Submit (POST),
 * nicht schon beim Aufruf des Links per GET – manche Mail-Programme/
 * Sicherheitsscanner rufen Links in E-Mails automatisch vorab ab, was sonst
 * ungewollt Anmeldungen bestätigen würde.
 */
final class ConfirmForm extends FormBase {

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
    return 'theater_newsletter_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?int $subscriber = NULL, ?string $token = NULL): array {
    $form_state->set('subscriber_id', $subscriber);
    $form_state->set('token', $token);

    $form['hinweis'] = [
      '#markup' => '<p>' . $this->t('Bitte bestätige deine Newsletter-Anmeldung.') . '</p>',
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Anmeldung bestätigen'),
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
      $form_state->setErrorByName('', $this->t('Dieser Bestätigungslink ist ungültig oder abgelaufen.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $subscriberId = $form_state->get('subscriber_id');
    $token = $form_state->get('token');

    if ($this->tokenManager->confirmWithToken($subscriberId, $token)) {
      $this->messenger()->addStatus($this->t('Danke! Deine Newsletter-Anmeldung ist jetzt bestätigt.'));
      return;
    }

    $this->messenger()->addError($this->t('Dieser Bestätigungslink ist ungültig oder abgelaufen.'));
  }

}
