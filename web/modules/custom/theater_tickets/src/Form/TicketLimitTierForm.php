<?php

declare(strict_types=1);

namespace Drupal\theater_tickets\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\theater_tickets\Entity\TicketLimitTierInterface;

/**
 * Formular zum Anlegen und Bearbeiten von Kauflimit-Stufen.
 */
final class TicketLimitTierForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\theater_tickets\Entity\TicketLimitTierInterface $tier */
    $tier = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name der Stufe'),
      '#default_value' => $tier->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $tier->id(),
      '#description' => $this->t('Für automatische Zuordnung per Rolle: Maschinenname exakt gleich dem Rollen-Maschinennamen wählen (z. B. "mitglied"), oder "default" für die Fallback-Stufe.'),
      '#machine_name' => [
        'exists' => '\Drupal\theater_tickets\Entity\TicketLimitTier::load',
      ],
      '#disabled' => !$tier->isNew(),
    ];

    $form['mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Zählmodus'),
      '#options' => [
        TicketLimitTierInterface::MODE_PER_PERFORMANCE => $this->t('Limit pro Vorstellung'),
        TicketLimitTierInterface::MODE_PER_SEASON => $this->t('Limit pro Saison (über alle Vorstellungen derselben Saison summiert)'),
        TicketLimitTierInterface::MODE_UNLIMITED => $this->t('Unbegrenzt'),
      ],
      '#default_value' => $tier->getMode(),
      '#required' => TRUE,
    ];

    $form['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximale Anzahl Plätze'),
      '#default_value' => $tier->getLimit(),
      '#min' => 1,
      '#states' => [
        'invisible' => [
          ':input[name="mode"]' => ['value' => TicketLimitTierInterface::MODE_UNLIMITED],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    $this->messenger()->addStatus($this->t('Kauflimit-Stufe "%label" wurde gespeichert.', [
      '%label' => $this->entity->label(),
    ]));

    return $result;
  }

}
