<?php

namespace Drupal\cacheflush_ui\Plugin\Action;

use Drupal\cacheflush_ui\CacheflushUIConstantsInterface;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Add menu item.
 *
 * @Action(
 *   id = "cacheflush_menu_action",
 *   label = @Translation("Add menu entry"),
 *   type = "cacheflush"
 * )
 */
class AddMenuCacheflush extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * The router builder.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routerBuilder;

  /**
   * The class constructor.
   *
   * @param array $configuration
   *   The configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $router_builder
   *   The router builder service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteBuilderInterface $router_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routerBuilder = $router_builder;
  }

  /**
   * The class create method.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   * @param array $configuration
   *   The configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return static
   *   The returned static form.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('router.builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $entities) {
    foreach ($entities as $entity) {
      $this->execute($entity);
    }
    $this->routerBuilder->rebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $entity->menu = CacheflushUIConstantsInterface::CACHEFLUSH_MENU;
    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $object->access('update', $account, TRUE);
    return $return_as_object ? $result : $result->isAllowed();
  }

}
