<?php

declare(strict_types=1);

namespace Drupal\theater_tickets\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\theater_tickets\Entity\Saal;
use Drupal\theater_tickets\Entity\SaalInterface;
use Drupal\theater_tickets\PlatzGeneratorService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin-Formular zur Massen-Erzeugung von Plätzen für einen Saal.
 */
final class SaalGenerateSeatsForm extends FormBase {

  public function __construct(
    private readonly PlatzGeneratorService $generator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('theater_tickets.platz_generator'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'theater_tickets_saal_generate_seats_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?SaalInterface $saal = NULL): array {
    if (!$saal instanceof SaalInterface) {
      $form['error'] = [
        '#markup' => $this->t('Saal nicht gefunden.'),
      ];
      return $form;
    }

    $form['saal_id'] = [
      '#type' => 'value',
      '#value' => $saal->id(),
    ];

    $form['info'] = [
      '#markup' => '<p>' . $this->t('Plätze für Saal "%label" generieren. Bereits vorhandene Reihe/Platznummer-Kombinationen werden übersprungen, ein erneuter Aufruf erzeugt also keine Duplikate.', [
        '%label' => $saal->label(),
      ]) . '</p>',
    ];

    $form['rows'] = [
      '#type' => 'number',
      '#title' => $this->t('Anzahl Reihen'),
      '#min' => 1,
      '#default_value' => $saal->get('rows') ?: 10,
      '#required' => TRUE,
    ];

    $form['seats_per_row'] = [
      '#type' => 'number',
      '#title' => $this->t('Plätze pro Reihe'),
      '#min' => 1,
      '#default_value' => $saal->get('seats_per_row') ?: 10,
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Plätze generieren'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $saal = Saal::load($form_state->getValue('saal_id'));
    if (!$saal instanceof SaalInterface) {
      $this->messenger()->addError($this->t('Saal nicht gefunden.'));
      return;
    }

    $rows = (int) $form_state->getValue('rows');
    $seatsPerRow = (int) $form_state->getValue('seats_per_row');

    $created = $this->generator->generate($saal, $rows, $seatsPerRow);

    $saal->set('rows', $rows);
    $saal->set('seats_per_row', $seatsPerRow);
    $saal->save();

    $this->messenger()->addStatus($this->t('@count neue Plätze für Saal "%label" erzeugt.', [
      '@count' => $created,
      '%label' => $saal->label(),
    ]));

    $form_state->setRedirectUrl($saal->toUrl('collection'));
  }

}
