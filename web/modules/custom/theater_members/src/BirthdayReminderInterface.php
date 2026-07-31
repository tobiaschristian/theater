<?php

declare(strict_types=1);

namespace Drupal\theater_members;

/**
 * Ermittelt bevorstehende Geburtstage und verschickt den Reminder.
 */
interface BirthdayReminderInterface {

  /**
   * Anzahl Tage Vorlauf vor dem Geburtstag, an dem erinnert wird.
   */
  public const REMINDER_LEAD_DAYS = 5;

  /**
   * Ermittelt aktive Mitglieder, deren Geburtstag in $daysAhead Tagen ist.
   *
   * "Aktiv" heißt: kein Austrittsdatum, oder Austrittsdatum liegt nicht in
   * der Vergangenheit.
   *
   * @return array<int, array{name: string, date: string, age: int}>
   *   Eine Liste von Treffern, sortiert nach Nachname.
   */
  public function getUpcomingBirthdays(int $daysAhead = self::REMINDER_LEAD_DAYS): array;

  /**
   * Verschickt den Reminder, falls heute noch nicht geschehen.
   *
   * Nutzt die State API als Tages-Sperre, damit ein mehrfacher Cron-Lauf
   * am selben Tag nicht mehrfach verschickt (bzw. mehrfach neu berechnet).
   *
   * @return bool
   *   TRUE, wenn eine Reminder-Mail verschickt wurde.
   */
  public function sendDueReminders(): bool;

}
