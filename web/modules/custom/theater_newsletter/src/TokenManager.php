<?php

declare(strict_types=1);

namespace Drupal\theater_newsletter;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\symfony_mailer\MailerPlusInterface;
use Drupal\theater_newsletter\Entity\NewsletterSubscriberInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Verwaltet Newsletter-Abonnenten: Anmeldung, Bestätigung, Abmeldung.
 *
 * Der pro Abonnent gespeicherte Token dient sowohl der Bestätigung als auch
 * der Abmeldung (ein langlebiges Geheimnis pro Datensatz statt zweier
 * separater Tokens) – nur der SHA-256-Hash landet in der Datenbank, der
 * Klartext-Token existiert ausschließlich im jeweiligen Mail-Link.
 */
final class TokenManager implements TokenManagerInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly MailerPlusInterface $mailerPlus,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function normalizeEmail(string $email): string {
    return mb_strtolower(trim($email));
  }

  /**
   * {@inheritdoc}
   */
  public function loadSubscriberByEmail(string $email): ?NewsletterSubscriberInterface {
    $storage = $this->entityTypeManager->getStorage('newsletter_subscriber');
    $subscribers = $storage->loadByProperties(['email' => $this->normalizeEmail($email)]);
    $subscriber = reset($subscribers);
    return $subscriber instanceof NewsletterSubscriberInterface ? $subscriber : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function subscribe(string $email, string $ip, ?int $uid = NULL): void {
    $email = $this->normalizeEmail($email);
    $subscriber = $this->loadSubscriberByEmail($email);

    if ($subscriber instanceof NewsletterSubscriberInterface && $subscriber->isConfirmed()) {
      // Bereits bestätigt: keine erneute Mail nötig, Anmeldeformular bleibt
      // aber nach außen unauffällig (siehe Interface-Doku).
      return;
    }

    if (!$subscriber instanceof NewsletterSubscriberInterface) {
      $storage = $this->entityTypeManager->getStorage('newsletter_subscriber');
      $subscriber = $storage->create([
        'email' => $email,
      ]);
    }

    $token = $this->generateToken();

    $subscriber->setStatus(NewsletterSubscriberInterface::STATUS_PENDING);
    $subscriber->setTokenHash($this->hashToken($token));
    $subscriber->setConsentIp($ip);
    $subscriber->setConfirmedTime(NULL);
    $subscriber->setUnsubscribedTime(NULL);
    if ($uid !== NULL) {
      $subscriber->setUserId($uid);
    }
    $subscriber->save();

    $this->sendConfirmationMail($subscriber, $token);
  }

  /**
   * {@inheritdoc}
   */
  public function findValidSubscriber(int $subscriberId, string $token): ?NewsletterSubscriberInterface {
    $subscriber = $this->loadSubscriber($subscriberId);
    if (!$subscriber instanceof NewsletterSubscriberInterface || !$subscriber->matchesToken($token)) {
      return NULL;
    }
    return $subscriber;
  }

  /**
   * {@inheritdoc}
   */
  public function confirmWithToken(int $subscriberId, string $token): bool {
    $subscriber = $this->loadSubscriber($subscriberId);
    if (!$subscriber instanceof NewsletterSubscriberInterface || !$subscriber->matchesToken($token)) {
      return FALSE;
    }

    if ($subscriber->isConfirmed()) {
      // Bereits bestätigt (z. B. Link doppelt geklickt): idempotent Erfolg.
      return TRUE;
    }

    $subscriber->setStatus(NewsletterSubscriberInterface::STATUS_CONFIRMED);
    $subscriber->setConfirmedTime($this->time->getRequestTime());
    $subscriber->save();

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function unsubscribeWithToken(int $subscriberId, string $token): bool {
    $subscriber = $this->loadSubscriber($subscriberId);
    if (!$subscriber instanceof NewsletterSubscriberInterface || !$subscriber->matchesToken($token)) {
      return FALSE;
    }

    if ($subscriber->isUnsubscribed()) {
      return TRUE;
    }

    $subscriber->setStatus(NewsletterSubscriberInterface::STATUS_UNSUBSCRIBED);
    $subscriber->setUnsubscribedTime($this->time->getRequestTime());
    $subscriber->save();

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function syncAccountLink(UserInterface $account): void {
    $email = $account->getEmail();
    if ($email === NULL || $email === '') {
      return;
    }

    $subscriber = $this->loadSubscriberByEmail($email);
    if (!$subscriber instanceof NewsletterSubscriberInterface || $subscriber->getUserId() !== NULL) {
      return;
    }

    $subscriber->setUserId((int) $account->id());
    $subscriber->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getSubscriberForAccount(UserInterface $account): ?NewsletterSubscriberInterface {
    if ($account->isAnonymous()) {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('newsletter_subscriber');
    $subscribers = $storage->loadByProperties(['uid' => $account->id()]);
    $subscriber = reset($subscribers);
    if ($subscriber instanceof NewsletterSubscriberInterface) {
      return $subscriber;
    }

    // Noch nicht per uid verknüpft, aber vielleicht per E-Mail zuordenbar
    // (z. B. Anmeldung vor dem Login, hook_user_login lief noch nicht).
    $email = $account->getEmail();
    return $email !== NULL && $email !== '' ? $this->loadSubscriberByEmail($email) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setSubscribedForAccount(UserInterface $account, bool $subscribed, string $ip): void {
    $subscriber = $this->getSubscriberForAccount($account);

    if (!$subscribed) {
      if ($subscriber instanceof NewsletterSubscriberInterface && !$subscriber->isUnsubscribed()) {
        $subscriber->setStatus(NewsletterSubscriberInterface::STATUS_UNSUBSCRIBED);
        $subscriber->setUnsubscribedTime($this->time->getRequestTime());
        $subscriber->save();
      }
      return;
    }

    if (!$subscriber instanceof NewsletterSubscriberInterface) {
      $email = $account->getEmail();
      if ($email === NULL || $email === '') {
        return;
      }
      $storage = $this->entityTypeManager->getStorage('newsletter_subscriber');
      $subscriber = $storage->create(['email' => $this->normalizeEmail($email)]);
      $subscriber->setTokenHash($this->hashToken($this->generateToken()));
    }

    if (!$subscriber->isConfirmed()) {
      $subscriber->setStatus(NewsletterSubscriberInterface::STATUS_CONFIRMED);
      $subscriber->setConfirmedTime($this->time->getRequestTime());
      $subscriber->setConsentIp($ip);
    }
    $subscriber->setUserId((int) $account->id());
    $subscriber->save();
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollectStalePending(int $maxAgeSeconds): int {
    $storage = $this->entityTypeManager->getStorage('newsletter_subscriber');
    $ids = $storage->getQuery()
      ->condition('status', NewsletterSubscriberInterface::STATUS_PENDING)
      ->condition('created', $this->time->getRequestTime() - $maxAgeSeconds, '<')
      ->accessCheck(FALSE)
      ->execute();

    if (!$ids) {
      return 0;
    }

    $storage->delete($storage->loadMultiple($ids));

    return count($ids);
  }

  /**
   * Lädt einen Abonnenten-Datensatz per ID.
   */
  private function loadSubscriber(int $subscriberId): ?NewsletterSubscriberInterface {
    $subscriber = $this->entityTypeManager->getStorage('newsletter_subscriber')->load($subscriberId);
    return $subscriber instanceof NewsletterSubscriberInterface ? $subscriber : NULL;
  }

  /**
   * Erzeugt einen kryptografisch sicheren Klartext-Token.
   */
  private function generateToken(): string {
    return bin2hex(random_bytes(32));
  }

  /**
   * Hasht einen Klartext-Token für die Speicherung.
   */
  private function hashToken(string $token): string {
    return hash('sha256', $token);
  }

  /**
   * Verschickt die Bestätigungsmail mit Klartext-Token im Link.
   *
   * Nutzt das gemeinsame HTML-Twig-Template "theater_newsletter_mail"
   * (siehe theater_newsletter.module::hook_theme()) über Mailer Plus, statt
   * eines klassischen hook_mail() – so bekommt die Mail automatisch die
   * saubere HTML-Hülle von Mailer Plus und lässt sich hier zentral pflegen.
   */
  private function sendConfirmationMail(NewsletterSubscriberInterface $subscriber, string $token): void {
    $url = Url::fromRoute('theater_newsletter.confirm', [
      'subscriber' => $subscriber->id(),
      'token' => $token,
    ], ['absolute' => TRUE])->toString();

    $email = $this->mailerPlus->newEmail('theater_newsletter.confirm')
      ->setTo($subscriber->getEmail());

    // setSubject()/setBody() sind erst ab der Build-Phase gültig, daher
    // per Callback statt direkt nach newEmail() (Init-Phase).
    $email->addCallback(static function ($email) use ($url): void {
      $email->setSubject(new TranslatableMarkup('Bitte bestätige deine Newsletter-Anmeldung'))
        ->setBody([
          '#theme' => 'theater_newsletter_mail',
          '#heading' => new TranslatableMarkup('Newsletter-Anmeldung bestätigen'),
          '#intro' => new TranslatableMarkup('Bitte bestätige deine Anmeldung zum Newsletter des Theatervereins Zapfendorf über den folgenden Link:'),
          '#button_url' => $url,
          '#button_label' => new TranslatableMarkup('Anmeldung bestätigen'),
          '#outro' => new TranslatableMarkup('Wenn du diese Anmeldung nicht ausgelöst hast, kannst du diese E-Mail einfach ignorieren.'),
        ]);
    });

    if (!$email->send()) {
      $this->logger->error('Bestätigungsmail an @email konnte nicht versendet werden: @error', [
        '@email' => $subscriber->getEmail(),
        '@error' => $email->getError(),
      ]);
    }
  }

}
