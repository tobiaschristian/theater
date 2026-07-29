<?php

declare(strict_types=1);

namespace Drupal\theater_newsletter;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Ergänzt Indizes für die Newsletter-Abonnent-Tabelle.
 *
 * Sowohl die Anmeldung (Duplikatsprüfung) als auch der Login-Abgleich
 * (hook_user_login) suchen Abonnenten per E-Mail-Adresse – ohne Index
 * würde das bei wachsender Tabelle zum Full-Table-Scan.
 */
final class NewsletterSubscriberStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE): array {
    $schema = parent::getEntitySchema($entity_type, $reset);

    $schema['newsletter_subscriber']['indexes'] += [
      'newsletter_subscriber__email' => ['email'],
      'newsletter_subscriber__uid' => ['uid'],
    ];

    return $schema;
  }

}
