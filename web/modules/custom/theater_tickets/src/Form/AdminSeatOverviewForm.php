<?php

declare(strict_types=1);

namespace Drupal\theater_tickets\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\theater_tickets\Entity\PlatzInterface;
use Drupal\theater_tickets\Entity\TicketInterface;
use Drupal\theater_tickets\SeatHoldManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin-Übersicht: Sitzplatzstatus einer Vorstellung, mit Freigabe-Aktionen.
 *
 * Erlaubt es Administratoren, fremde Reservierungen aufzuheben oder ein
 * bereits verkauftes Ticket zu stornieren (z. B. nach einer telefonischen
 * Absage), damit der Platz wieder verfügbar wird.
 */
final class AdminSeatOverviewForm extends FormBase {

  private const WRAPPER_ID = 'theater-admin-seat-overview-wrapper';

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private SeatHoldManagerInterface $seatHoldManager,
    private AccountProxyInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('theater_tickets.seat_hold_manager'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'theater_tickets_admin_seat_overview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    $form_state->disableCache();

    if (!$node instanceof NodeInterface || $node->bundle() !== 'vorstellung') {
      $form['error'] = ['#markup' => $this->t('Vorstellung nicht gefunden.')];
      return $form;
    }

    $form['#node_id'] = (int) $node->id();
    $form['#title'] = $this->t('Sitzplatzübersicht: %title', ['%title' => $node->label()]);

    if (!$node->hasField('field_saal') || $node->get('field_saal')->isEmpty()) {
      $form['error'] = ['#markup' => $this->t('Für diese Vorstellung ist kein Saal hinterlegt.')];
      return $form;
    }

    $saalId = $node->get('field_saal')->target_id;
    $seats = $this->entityTypeManager->getStorage('theater_platz')->loadByProperties(['saal' => $saalId]);
    usort($seats, static fn (PlatzInterface $a, PlatzInterface $b) => [$a->getRowLabel(), $a->getSeatNumber()] <=> [$b->getRowLabel(), $b->getSeatNumber()]);

    $holds = $this->seatHoldManager->getActiveHoldsForPerformance($node);
    $holdsBySeatId = [];
    foreach ($holds as $hold) {
      $holdsBySeatId[(int) $hold['seat_id']] = $hold;
    }

    $ticketStorage = $this->entityTypeManager->getStorage('theater_ticket');
    $ticketIds = $ticketStorage->getQuery()
      ->condition('performance', $node->id())
      ->accessCheck(FALSE)
      ->execute();
    $ticketsBySeatId = [];
    foreach ($ticketStorage->loadMultiple($ticketIds) as $ticket) {
      /** @var \Drupal\theater_tickets\Entity\TicketInterface $ticket */
      $ticketsBySeatId[$ticket->getSeatId()] = $ticket;
    }

    $userStorage = $this->entityTypeManager->getStorage('user');

    $form['overview'] = [
      '#type' => 'container',
      '#attributes' => ['id' => self::WRAPPER_ID],
    ];
    $form['overview']['messages'] = ['#type' => 'status_messages'];

    $form['overview']['summary'] = [
      '#markup' => '<p>' . $this->t('@sold verkauft, @held reserviert, @free frei (von @total Plätzen).', [
        '@sold' => count($ticketsBySeatId),
        '@held' => count($holdsBySeatId),
        '@free' => count($seats) - count($ticketsBySeatId) - count($holdsBySeatId),
        '@total' => count($seats),
      ]) . '</p>',
    ];

    $table = [
      '#type' => 'table',
      '#header' => [
        $this->t('Platz'),
        $this->t('Status'),
        $this->t('Nutzer'),
        $this->t('Aktion'),
      ],
    ];

    foreach ($seats as $seat) {
      /** @var \Drupal\theater_tickets\Entity\PlatzInterface $seat */
      $seatId = (int) $seat->id();
      $row = ['label' => ['#markup' => $seat->getDisplayLabel()]];

      if (isset($ticketsBySeatId[$seatId])) {
        /** @var \Drupal\theater_tickets\Entity\TicketInterface $ticket */
        $ticket = $ticketsBySeatId[$seatId];
        $user = $userStorage->load($ticket->getOwnerUserId());
        $row['status'] = ['#markup' => $this->t('Verkauft')];
        $row['user'] = ['#markup' => $user ? $user->getDisplayName() : $this->t('unbekannt')];
        $row['action'] = [
          '#type' => 'submit',
          '#value' => $this->t('Ticket stornieren'),
          '#name' => 'cancel_ticket:' . $seatId,
          '#ticket_id' => (int) $ticket->id(),
          '#submit' => ['::cancelTicketSubmit'],
          '#ajax' => ['callback' => '::ajaxRefresh', 'wrapper' => self::WRAPPER_ID],
        ];
      }
      elseif (isset($holdsBySeatId[$seatId])) {
        $hold = $holdsBySeatId[$seatId];
        $user = $userStorage->load((int) $hold['uid']);
        $row['status'] = ['#markup' => $this->t('Reserviert bis @time', [
          '@time' => date('H:i:s', (int) $hold['expires']),
        ])];
        $row['user'] = ['#markup' => $user ? $user->getDisplayName() : $this->t('unbekannt')];
        $row['action'] = [
          '#type' => 'submit',
          '#value' => $this->t('Reservierung freigeben'),
          '#name' => 'release_hold:' . $seatId,
          '#hold_id' => (int) $hold['id'],
          '#submit' => ['::releaseHoldSubmit'],
          '#ajax' => ['callback' => '::ajaxRefresh', 'wrapper' => self::WRAPPER_ID],
        ];
      }
      else {
        $row['status'] = ['#markup' => $this->t('Frei')];
        $row['user'] = ['#markup' => ''];
        $row['action'] = ['#markup' => ''];
      }

      $table[$seatId] = $row;
    }

    $form['overview']['table'] = $table;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Logik läuft über die spezifischen ::*Submit-Handler der Buttons.
  }

  /**
   * AJAX-Callback: gibt den aktualisierten Übersichtsbereich zurück.
   */
  public function ajaxRefresh(array &$form, FormStateInterface $form_state): array {
    return $form['overview'];
  }

  /**
   * Submit-Handler: fremde oder eigene Reservierung freigeben.
   */
  public function releaseHoldSubmit(array &$form, FormStateInterface $form_state): void {
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
   * Submit-Handler: verkauftes Ticket stornieren, Platz wird wieder frei.
   */
  public function cancelTicketSubmit(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $ticketId = (int) $trigger['#ticket_id'];

    $ticket = $this->entityTypeManager->getStorage('theater_ticket')->load($ticketId);
    if ($ticket instanceof TicketInterface) {
      $ticket->delete();
      $this->messenger()->addStatus($this->t('Ticket wurde storniert, der Platz ist wieder frei.'));
    }
    else {
      $this->messenger()->addError($this->t('Ticket nicht gefunden.'));
    }

    $form_state->setRebuild();
  }

}
