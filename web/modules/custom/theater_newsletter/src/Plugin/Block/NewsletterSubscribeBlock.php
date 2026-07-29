<?php

declare(strict_types=1);

namespace Drupal\theater_newsletter\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\theater_newsletter\Form\SubscribeForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Zeigt das Newsletter-Anmeldeformular als platzierbaren Block.
 *
 * @Block(
 *   id = "theater_newsletter_subscribe",
 *   admin_label = @Translation("Newsletter-Anmeldung"),
 *   category = @Translation("Formulare"),
 * )
 */
final class NewsletterSubscribeBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly FormBuilderInterface $formBuilder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = $this->formBuilder->getForm(SubscribeForm::class);

    // Nicht cachen: Das Formular enthält einen personenbezogenen CSRF-
    // Token, der nicht in einer gerenderten Seite gecacht werden darf.
    $build['#cache']['max-age'] = 0;

    return $build;
  }

}
