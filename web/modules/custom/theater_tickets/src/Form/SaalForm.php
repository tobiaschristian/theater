<?php

declare(strict_types=1);

namespace Drupal\theater_tickets\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Formular zum Anlegen und Bearbeiten von Sälen.
 */
final class SaalForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\theater_tickets\Entity\SaalInterface $saal */
    $saal = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name des Saals'),
      '#default_value' => $saal->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $saal->id(),
      '#machine_name' => [
        'exists' => '\Drupal\theater_tickets\Entity\Saal::load',
      ],
      '#disabled' => !$saal->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Beschreibung'),
      '#default_value' => $saal->get('description'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    $this->messenger()->addStatus($this->t('Saal "%label" wurde gespeichert.', [
      '%label' => $this->entity->label(),
    ]));

    return $result;
  }

}
