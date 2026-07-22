<?php

namespace Drupal\cacheflush\Controller;

use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Drupal\cacheflush_entity\Entity\CacheflushEntity;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\PhpStorage\PhpStorageFactory;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Returns responses for Cacheflush routes.
 */
class CacheflushApi extends ControllerBase {

  /**
   * The Logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The Current User service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Drupal container.
   *
   * @var null|\Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * CacheflushApi constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal container.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extension_list_module
   *   The module extension list.
   */
  public function __construct(
    ContainerInterface $container,
    MessengerInterface $messenger,
    ModuleExtensionList $extension_list_module,
    LoggerChannelFactoryInterface $logger_factory,
    AccountProxyInterface $current_user
  ) {
    $this->container = $container;
    $this->messenger = $messenger;
    $this->moduleExtensionList = $extension_list_module;
    $this->logger      = $logger_factory->get('cacheflush');
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container,
      $container->get('messenger'),
      $container->get('extension.list.module'),
      $container->get('logger.factory'),
      $container->get('current_user')
    );
  }

  /**
   * Clear all caches.
   *
   * @see drupal_flush_all_caches()
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect path.
   */
  public function clearAll() {
    drupal_flush_all_caches();
    $this->messenger->addMessage($this->t('Cache cleared.'));

    // Log the cache clear event.
    $username = $this->currentUser->getAccountName();
    $this->logger->notice('Caches were cleared from the Cacheflush module by @username.', ['@username' => $username]);

    return $this->redirectUrl();
  }

  /**
   * Clear cache preset by cacheflush entity id.
   *
   * @param \Drupal\cacheflush_entity\Entity\CacheflushEntity $cacheflush
   *   Caheflush entity to run.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect path.
   */
  public function clearById(CacheflushEntity $cacheflush) {
    $this->clearPresetCache($cacheflush);
    return $this->redirectUrl();
  }

  /**
   * Based on settings decide witch clear cache function to be called.
   *
   * @param \Drupal\cacheflush_entity\Entity\CacheflushEntity $entity
   *   Preset id to do clear cache for.
   */
  public function clearPresetCache(CacheflushEntity $entity) {
    $this->checkError($entity);

    $this->moduleHandler()->invokeAll('cacheflush_before_clear', [$entity]);

    $presets = $entity->getData();
    if ($presets) {
      foreach ($presets as $cache) {
        foreach ($cache['functions'] as $value) {
          if (is_callable($value['#name'])) {
            call_user_func_array($value['#name'], $value['#params']);
          }
          else {
            $this->getLogger('CACHEFLUSH')->warning(
              $this->t("Function cannot be called: @name", ['@name' => $value['#name']])
            );
          }
        }
      }
    }

    $this->messenger->addMessage($this->t("All predefined cache options in @name was cleared.", ['@name' => $entity->getTitle()]));
    $this->moduleHandler()->invokeAll('cacheflush_after_clear', [$entity]);
  }

  /**
   * Return a list of cache options to be cleared.
   *
   * @return array
   *   List cache options.
   */
  public function getOptionList() {
    $bins = $this->createTabOptions();
    $other = $this->moduleHandler()->invokeAll('cacheflush_tabs_options');
    return array_merge($bins, $other);
  }

  /**
   * Create option array for preset.
   *
   * @return array
   *   Preset options.
   */
  public function createTabOptions() {
    $core = array_flip($this->coreBinMapping());
    foreach ($this->container->getParameter('cache_bins') as $service_id => $bin) {
      $options[$bin] = [
        'description' => $this->t('Storage for the cache API.'),
        'category' => isset($core[$bin]) ? 'vertical_tabs_core' : 'vertical_tabs_custom',
        'functions' => [
          '0' => [
            '#name' => '\Drupal\cacheflush\Controller\CacheflushApi::clearBinCache',
            '#params' => [$service_id],
          ],
        ],
      ];
    }
    return isset($options) ? $options : [];
  }

  /**
   * Clear cache by service id.
   *
   * @param string $service_id
   *   Name of cache service.
   * @param string $function
   *   Function to be called.
   * @param string $cid
   *   Cache ID.
   */
  public function clearBinCache($service_id, $function = 'deleteAll', $cid = NULL) {
    $this->container->get($service_id)->{$function}($cid);
  }

  /**
   * Clear cache by service id.
   *
   * @param string $type
   *   The name for which the storage should be returned. Defaults to 'default'
   *   The name is also used as the storage bin if one is not specified in the
   *   configuration.
   * @param string $function
   *   Function to be called.
   */
  public function clearStorageCache($type, $function = 'deleteAll') {
    PhpStorageFactory::get($type)->{$function}();
  }

  /**
   * Clear modules cache.
   */
  public function clearModuleCache() {
    $module_handler = $this->moduleHandler();

    // Invalidate the container.
    $this->container->get('kernel')->invalidateContainer();

    // Rebuild module and theme data.
    $module_data = $this->moduleExtensionList->reset()->getList();
    /** @var \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler */
    $theme_handler = $this->container->get('theme_handler');
    $theme_handler->refreshInfo();
    // In case the active theme gets requested later in the same request we need
    // to reset the theme manager.
    $this->container->get('theme.manager')->resetActiveTheme();

    // Rebuild and reboot a new kernel. A simple DrupalKernel reboot is not
    // sufficient, since the list of enabled modules might have been adjusted
    // above due to changed code.
    $files = [];
    foreach ($module_data as $name => $extension) {
      if ($extension->status) {
        $files[$name] = $extension;
      }
    }
    $this->container->get('kernel')->updateModules(
      $this->moduleHandler()->getModuleList(), $files
    );
    // New container, new module handler.
    $module_handler = $this->moduleHandler();

    // Ensure that all modules that are currently supposed to be enabled are
    // actually loaded.
    $module_handler->loadAll();

    // Rebuild all information based on new module data.
    $module_handler->invokeAll('rebuild');

    // Re-initialize the maintenance theme, if the current request attempted to
    // use it. Unlike regular usages of this function, the installer and update
    // scripts need to flush all caches during GET requests/page building.
    if (function_exists('_drupal_maintenance_theme')) {
      $this->container->get('theme.manager')->resetActiveTheme();
      drupal_maintenance_theme();
    }
  }

  /**
   * List of the core cache bin.
   */
  public function coreBinMapping() {
    $core_bins = [
      'bootstrap',
      'config',
      'data',
      'default',
      'discovery',
      'dynamic_page_cache',
      'entity',
      'menu',
      'render',
      'migrate',
      'rest',
      'toolbar',
    ];
    return $core_bins;
  }

  /**
   * Check if entity exists and is enabled.
   *
   * @param \Drupal\cacheflush_entity\Entity\CacheflushEntity $entity
   *   Cacheflush entity.
   */
  private function checkError(CacheflushEntity $entity) {
    if (!$entity) {
      $this->messenger->addMessage($this->t('Invalid entity ID.'), 'error');
      throw new HttpException('404');
    }
    if ($entity->getStatus() == 0) {
      $this->messenger->addMessage($this->t('This entity is disabled.'), 'error');
      throw new HttpException('403');
    }
  }

  /**
   * Generate redirect URL.
   *
   * @global string $base_url
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect path.
   */
  private function redirectUrl() {
    global $base_url;

    $path = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : NULL;
    if (empty($_SERVER['HTTP_REFERER'])) {
      $path = $base_url;
    }
    return new RedirectResponse($path);
  }

  /**
   * Invalidate cache tags.
   *
   * @param string $tags
   *   List of cache tags, formatted as comma separated string.
   */
  public function clearCacheTags($tags) {
    Cache::invalidateTags(explode(',', $tags));
  }

}
