<?php

declare(strict_types=1);

namespace Drupal\theater_members;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\symfony_mailer\MailerPlusInterface;
use Psr\Log\LoggerInterface;

/**
 * Ermittelt bevorstehende Geburtstage und verschickt den Reminder.
 */
final class BirthdayReminder implements BirthdayReminderInterface {

  /**
   * State-Key: Datum (Y-m-d) des letzten erfolgreichen Cron-Durchlaufs.
   */
  private const STATE_LAST_RUN = 'theater_members.birthday_reminder_last_run';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly MailerPlusInterface $mailerPlus,
    private readonly StateInterface $state,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getUpcomingBirthdays(int $daysAhead = self::REMINDER_LEAD_DAYS): array {
    $today = new \DateTimeImmutable('@' . $this->time->getRequestTime());
    $target = $today->modify("+{$daysAhead} days");
    $targetMonthDay = $target->format('m-d');
    $todayString = $today->format('Y-m-d');

    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->condition('type', 'mitglied')
      ->exists('field_geburtsdatum')
      ->accessCheck(FALSE)
      ->execute();

    if (!$nids) {
      return [];
    }

    $matches = [];
    foreach ($storage->loadMultiple($nids) as $node) {
      /** @var \Drupal\node\NodeInterface $node */
      $birthdayValue = (string) $node->get('field_geburtsdatum')->value;
      if ($birthdayValue === '' || substr($birthdayValue, 5, 5) !== $targetMonthDay) {
        continue;
      }

      $exitValue = (string) $node->get('field_austrittsdatum')->value;
      if ($exitValue !== '' && $exitValue < $todayString) {
        // Ausgetreten: kein Reminder mehr.
        continue;
      }

      $birthYear = (int) substr($birthdayValue, 0, 4);
      $matches[] = [
        'name' => $this->getMemberName($node),
        'date' => $target->format('d.m.Y'),
        'age' => (int) $target->format('Y') - $birthYear,
      ];
    }

    usort($matches, static fn (array $a, array $b) => $a['name'] <=> $b['name']);

    return $matches;
  }

  /**
   * {@inheritdoc}
   */
  public function sendDueReminders(): bool {
    $todayString = (new \DateTimeImmutable('@' . $this->time->getRequestTime()))->format('Y-m-d');

    if ($this->state->get(self::STATE_LAST_RUN) === $todayString) {
      // Heute schon gelaufen, egal ob mit oder ohne Treffer.
      return FALSE;
    }
    $this->state->set(self::STATE_LAST_RUN, $todayString);

    $matches = $this->getUpcomingBirthdays();
    if (!$matches) {
      return FALSE;
    }

    $recipients = $this->configFactory->get('theater_members.settings')->get('reminder_recipients') ?? [];
    if (!$recipients) {
      $this->logger->warning('Geburtstags-Reminder: @count bevorstehende Geburtstage, aber keine Empfänger konfiguriert.', [
        '@count' => count($matches),
      ]);
      return FALSE;
    }

    $email = $this->mailerPlus->newEmail('theater_members.birthday_reminder')
      ->setTo($recipients);

    $email->addCallback(static function ($email) use ($matches): void {
      $email->setSubject(new TranslatableMarkup('Bevorstehende Geburtstage in @days Tagen', ['@days' => BirthdayReminderInterface::REMINDER_LEAD_DAYS]))
        ->setBody([
          '#theme' => 'theater_members_birthday_mail',
          '#heading' => new TranslatableMarkup('Bevorstehende Geburtstage'),
          '#intro' => new TranslatableMarkup('Diese Mitglieder haben in @days Tagen Geburtstag:', ['@days' => BirthdayReminderInterface::REMINDER_LEAD_DAYS]),
          '#members' => $matches,
          '#outro' => new TranslatableMarkup('Automatische Erinnerung der Mitgliederverwaltung.'),
        ]);
    });

    if (!$email->send()) {
      $this->logger->error('Geburtstags-Reminder konnte nicht versendet werden: @error', ['@error' => $email->getError()]);
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Baut den Anzeigenamen aus Vor- und Nachname.
   */
  private function getMemberName(NodeInterface $node): string {
    $vorname = (string) $node->get('field_vorname')->value;
    $nachname = (string) $node->get('field_nachname')->value;
    return trim("$vorname $nachname");
  }

}
