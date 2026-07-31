<?php

declare(strict_types=1);

namespace Drupal\theater_members\Form;

use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Einstellungen für die Mitgliederverwaltung: Empfänger des Geburtstags-Reminders.
 */
final class MembersSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    private readonly EmailValidatorInterface $emailValidator,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('email.validator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['theater_members.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'theater_members_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $recipients = $this->config('theater_members.settings')->get('reminder_recipients') ?? [];

    $form['reminder_recipients'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Empfänger-E-Mail-Adressen für den Geburtstags-Reminder'),
      '#description' => $this->t('Eine E-Mail-Adresse pro Zeile. Diese Adressen erhalten 5 Tage vor dem Geburtstag eines aktiven Mitglieds eine Erinnerung.'),
      '#default_value' => implode("\n", $recipients),
      '#rows' => 6,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $lines = $this->parseRecipients((string) $form_state->getValue('reminder_recipients'));
    foreach ($lines as $email) {
      if (!$this->emailValidator->isValid($email)) {
        $form_state->setErrorByName('reminder_recipients', $this->t('"@email" ist keine gültige E-Mail-Adresse.', ['@email' => $email]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $recipients = $this->parseRecipients((string) $form_state->getValue('reminder_recipients'));

    $this->config('theater_members.settings')
      ->set('reminder_recipients', array_values(array_unique($recipients)))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Zerlegt die Textarea in eine bereinigte Liste von E-Mail-Adressen.
   */
  private function parseRecipients(string $raw): array {
    $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
    $lines = array_map('trim', $lines);
    return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
  }

}
