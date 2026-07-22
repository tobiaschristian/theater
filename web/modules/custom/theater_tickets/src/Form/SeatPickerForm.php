<?php

declare(strict_types=1);

namespace Drupal\theater_tickets\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\theater_tickets\Entity\PlatzInterface;
use Drupal\theater_tickets\PurchaseServiceInterface;
use Drupal\theater_tickets\SeatHoldManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Test-Oberfläche: Sitzplatzübersicht mit Reservieren/Freigeben/Kaufen.
 *
 * Dient in Phase 1 dazu, den kompletten Reservierungs-/Kauf-Ablauf ohne
 * Drupal Commerce manuell durchspielen zu können.
 */
final class SeatPickerForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly SeatHoldManagerInterface $seatHoldManager,
    private readonly PurchaseServiceInterface $purchaseService,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('theater_tickets.seat_hold_manager'),
      $container->get('theater_tickets.purchase'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'theater_tickets_seat_picker_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if (!$node instanceof NodeInterface || $node->bundle() !== 'vorstellung') {
      $form['error'] = ['#markup' => $this->t('Vorstellung nicht gefunden.')];
      return $form;
    }

    $form['#node_id'] = (int) $node->id();

    if (!$node->hasField('field_saal') || $node->get('field_saal')->isEmpty()) {
      $form['error'] = ['#markup' => $this->t('Für diese Vorstellung ist kein Saal hinterlegt.')];
      return $form;
    }

    $saalId = $node->get('field_saal')->target_id;

    if ($this->currentUser->isAnonymous()) {
      $form['login_notice'] = [
        '#markup' => '<p>' . $this->t('Bitte melden Sie sich an, um Plätze zu reservieren.') . '</p>',
      ];
    }

    $seats = $this->entityTypeManager->getStorage('theater_platz')->loadByProperties(['saal' => $saalId]);
    usort($seats, static fn (PlatzInterface $a, PlatzInterface $b) => [$a->getRowLabel(), $a->getSeatNumber()] <=> [$b->getRowLabel(), $b->getSeatNumber()]);

    $myHolds = $this->currentUser->isAnonymous() ? [] : $this->seatHoldManager->getActiveHoldsForUser($this->currentUser, $node);
    $myHoldSeatIds = array_column($myHolds, 'id', 'seat_id');

    $form['seats'] = ['#type' => 'container', '#attributes' => ['class' => ['theater-seat-list']]];

    foreach ($seats as $seat) {
      /** @var \Drupal\theater_tickets\Entity\PlatzInterface $seat */
      $seatId = (int) $seat->id();
      $row = ['#type' => 'container', '#attributes' => ['class' => ['theater-seat-row']]];
      $row['label'] = ['#markup' => $seat->getDisplayLabel()];

      if ($seat->getCategory() === 'gang') {
        $row['note'] = ['#markup' => ' <em>(' . $this->t('Platz im Gang, muss in den Pausen frei gemacht werden') . ')</em>'];
      }

      if (isset($myHoldSeatIds[$seatId])) {
        $row['status'] = ['#markup' => ' — ' . $this->t('von Ihnen reserviert') . ' '];
        $row['release'] = [
          '#type' => 'submit',
          '#value' => $this->t('Freigeben'),
          '#name' => 'release:' . $seatId,
          '#seat_id' => $seatId,
          '#hold_id' => (int) $myHoldSeatIds[$seatId],
          '#submit' => ['::releaseSubmit'],
        ];
      }
      elseif ($this->seatHoldManager->isHeld($seat, $node)) {
        $row['status'] = ['#markup' => ' — ' . $this->t('belegt')];
      }
      else {
        $row['hold'] = [
          '#type' => 'submit',
          '#value' => $this->t('Reservieren'),
          '#name' => 'hold:' . $seatId,
          '#seat_id' => $seatId,
          '#submit' => ['::holdSubmit'],
          '#disabled' => $this->currentUser->isAnonymous(),
        ];
      }

      $form['seats'][$seatId] = $row;
    }

    if (!empty($myHolds)) {
      $form['purchase'] = [
        '#type' => 'submit',
        '#value' => $this->t('@count Platz/Plätze jetzt kaufen', ['@count' => count($myHolds)]),
        '#name' => 'purchase',
        '#submit' => ['::purchaseSubmit'],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Alle eigentliche Logik läuft über die spezifischen ::*Submit-Handler
    // der einzelnen Buttons; dies ist nur der Pflicht-Fallback von FormBase.
  }

  /**
   * Submit-Handler: Platz reservieren.
   */
  public function holdSubmit(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $seatId = (int) $trigger['#seat_id'];
    $node = $this->entityTypeManager->getStorage('node')->load($form['#node_id']);
    $seat = $this->entityTypeManager->getStorage('theater_platz')->load($seatId);

    if (!$seat instanceof PlatzInterface || !$node instanceof NodeInterface) {
      $this->messenger()->addError($this->t('Platz oder Vorstellung nicht gefunden.'));
      return;
    }

    $result = $this->seatHoldManager->createHold($seat, $node, $this->currentUser);

    if ($result->success) {
      $this->messenger()->addStatus($this->t('Platz "%label" wurde für 5 Minuten für Sie reserviert.', [
        '%label' => $seat->getDisplayLabel(),
      ]));
    }
    else {
      $this->messenger()->addError($this->reasonToMessage($result->reason));
    }

    $form_state->setRebuild();
  }

  /**
   * Submit-Handler: eigene Reservierung freigeben.
   */
  public function releaseSubmit(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $holdId = (int) $trigger['#hold_id'];

    if ($this->seatHoldManager->releaseHold($holdId, $this->currentUser)) {
      $this->messenger()->addStatus($this->t('Reservierung wurde freigegeben.'));
    }
    else {
      $this->messenger()->addError($this->t('Reservierung konnte nicht freigegeben werden.'));
    }

    $form_state->setRebuild();
  }

  /**
   * Submit-Handler: alle eigenen Reservierungen für diese Vorstellung kaufen.
   */
  public function purchaseSubmit(array &$form, FormStateInterface $form_state): void {
    $node = $this->entityTypeManager->getStorage('node')->load($form['#node_id']);
    $holds = $this->seatHoldManager->getActiveHoldsForUser($this->currentUser, $node);
    $holdIds = array_column($holds, 'id');

    $result = $this->purchaseService->purchase($holdIds, $this->currentUser);

    if ($result->success) {
      $this->messenger()->addStatus($this->t('Kauf erfolgreich: @count Ticket(s) ausgestellt.', ['@count' => count($result->ticketIds)]));
    }
    else {
      $this->messenger()->addError($this->t('Kauf abgebrochen: mindestens ein Platz ist nicht mehr gültig reserviert. Bitte erneut versuchen.'));
    }

    $form_state->setRebuild();
  }

  /**
   * Übersetzt einen internen Fehlergrund in eine Nutzermeldung.
   */
  private function reasonToMessage(?string $reason): string {
    return match ($reason) {
      'seat_taken' => (string) $this->t('Dieser Platz wurde soeben von jemand anderem reserviert.'),
      'quota_exceeded' => (string) $this->t('Sie haben Ihr Kauflimit erreicht.'),
      'login_required' => (string) $this->t('Bitte melden Sie sich an, um Plätze zu reservieren.'),
      default => (string) $this->t('Die Reservierung konnte nicht angelegt werden.'),
    };
  }

}
