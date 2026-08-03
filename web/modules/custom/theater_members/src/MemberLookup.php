<?php

declare(strict_types=1);

namespace Drupal\theater_members;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Verknüpft Benutzerkonten mit Mitglied-Nodes.
 */
final class MemberLookup implements MemberLookupInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function findByUserId(int $uid): ?NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->condition('type', 'mitglied')
      ->condition('field_user_account', $uid)
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    if (!$nids) {
      return NULL;
    }

    $node = $storage->load(reset($nids));
    return $node instanceof NodeInterface ? $node : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function findByEmail(string $email): ?NodeInterface {
    $email = trim($email);
    if ($email === '') {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->condition('type', 'mitglied')
      ->condition('field_email', $email)
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    if (!$nids) {
      return NULL;
    }

    $node = $storage->load(reset($nids));
    return $node instanceof NodeInterface ? $node : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrCreateForAccount(UserInterface $account): NodeInterface {
    $uid = (int) $account->id();

    $existing = $this->findByUserId($uid);
    if ($existing instanceof NodeInterface) {
      return $existing;
    }

    $email = $account->getEmail() ?? '';
    $unlinked = $email !== '' ? $this->findByEmail($email) : NULL;
    if ($unlinked instanceof NodeInterface && $unlinked->get('field_user_account')->isEmpty()) {
      $unlinked->set('field_user_account', $uid);
      $unlinked->save();
      return $unlinked;
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $node = $storage->create([
      'type' => 'mitglied',
      'field_vorname' => $this->getAccountFieldValue($account, 'field_vorname'),
      'field_nachname' => $this->getAccountFieldValue($account, 'field_nachname'),
      'field_email' => $email,
      'field_eintrittsdatum' => [
        'value' => (new \DateTimeImmutable('@' . $this->time->getRequestTime()))->format('Y-m-d'),
      ],
      'field_mitgliedschaftsart' => 'mitglied',
      'field_user_account' => $uid,
    ]);
    $node->save();

    return $node;
  }

  /**
   * Liest einen Feldwert vom Konto, falls das Feld existiert.
   */
  private function getAccountFieldValue(UserInterface $account, string $fieldName): string {
    if (!$account->hasField($fieldName) || $account->get($fieldName)->isEmpty()) {
      return '';
    }
    return (string) $account->get($fieldName)->value;
  }

}
