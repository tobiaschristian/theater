<?php

declare(strict_types=1);

namespace Drupal\theater_newsletter\Form;

use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\theater_newsletter\TokenManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Öffentliches Newsletter-Anmeldeformular (anonym oder eingeloggt nutzbar).
 */
final class SubscribeForm extends FormBase {

  /**
   * Maximal erlaubte Anmeldeversuche pro IP-Adresse und Zeitfenster.
   *
   * Verhindert, dass das Formular zum Mail-Bombing einer fremden Adresse
   * mit wiederholten Bestätigungsmails missbraucht wird.
   */
  private const FLOOD_LIMIT = 5;
  private const FLOOD_WINDOW = 3600;

  public function __construct(
    private readonly TokenManagerInterface $tokenManager,
    private readonly FloodInterface $flood,
    private readonly AccountProxyInterface $currentUser,
    private readonly RequestStack $newsletterRequestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('theater_newsletter.token_manager'),
      $container->get('flood'),
      $container->get('current_user'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'theater_newsletter_subscribe_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('E-Mail-Adresse'),
      '#required' => TRUE,
      '#default_value' => $this->currentUser->isAnonymous() ? '' : ($this->currentUser->getEmail() ?? ''),
    ];

    // Honeypot: für Menschen unsichtbares Feld, das Formular-Bots häufig
    // trotzdem ausfüllen. Bleibt es leer, ist der Absender vermutlich kein
    // Bot.
    // Inline statt nur per CSS-Klasse versteckt, damit das gesamte
    // Form-Item (inkl. Label) unabhängig vom aktiven Theme (das
    // core-Hilfsklassen wie .visually-hidden eventuell gar nicht lädt)
    // zuverlässig unsichtbar bleibt.
    $form['website'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Website'),
      '#wrapper_attributes' => [
        'style' => 'position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;',
        'aria-hidden' => 'true',
      ],
      '#attributes' => [
        'tabindex' => '-1',
        'autocomplete' => 'off',
      ],
      '#required' => FALSE,
    ];

    $form['hinweis'] = [
      '#type' => 'item',
      '#markup' => $this->t('Du erhältst eine E-Mail mit einem Bestätigungslink. Erst nach dem Klick darauf bist du angemeldet.'),
      '#weight' => 10,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Newsletter abonnieren'),
      '#weight' => 20,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $ip = $this->newsletterRequestStack->getCurrentRequest()?->getClientIp() ?? '0.0.0.0';

    if ((string) $form_state->getValue('website') !== '') {
      // Honeypot ausgefüllt: keinen Hinweis geben, einfach ohne Wirkung
      // abbrechen.
      $form_state->setValue('theater_newsletter_bot_detected', TRUE);
      return;
    }

    if (!$this->flood->isAllowed('theater_newsletter.subscribe', self::FLOOD_LIMIT, self::FLOOD_WINDOW, $ip)) {
      $form_state->setErrorByName('email', $this->t('Zu viele Anmeldeversuche. Bitte versuche es später erneut.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $ip = $this->newsletterRequestStack->getCurrentRequest()?->getClientIp() ?? '0.0.0.0';

    if ($form_state->getValue('theater_newsletter_bot_detected')) {
      $this->messenger()->addStatus($this->genericConfirmationMessage());
      return;
    }

    $this->flood->register('theater_newsletter.subscribe', self::FLOOD_WINDOW, $ip);

    $uid = $this->currentUser->isAnonymous() ? NULL : (int) $this->currentUser->id();
    $this->tokenManager->subscribe((string) $form_state->getValue('email'), $ip, $uid);

    $this->messenger()->addStatus($this->genericConfirmationMessage());
  }

  /**
   * Immer identische Erfolgsmeldung, unabhängig vom internen Zustand
   * (verhindert E-Mail-Enumeration über das öffentliche Formular).
   */
  private function genericConfirmationMessage(): \Stringable {
    return $this->t('Falls diese E-Mail-Adresse noch nicht für den Newsletter angemeldet ist, erhältst du in Kürze eine Bestätigungsmail.');
  }

}
