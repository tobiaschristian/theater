<?php

namespace Drupal\cacheflush_drush\Commands;

use Drupal\cacheflush\Controller\CacheflushApi;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drush\Commands\DrushCommands;

/**
 * Defines drush commands for cacheflush_drush module.
 */
class CacheflushDrushCommands extends DrushCommands {

  /**
   * The logger channel for this service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * CacheflushDrushCommands constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct();
    $this->logger = $logger_factory->get('qa_accounts');
  }

  /**
   * Clear preset cache by id.
   *
   * @command cacheflush
   * 
   * @param string|int $id
   *   An optional argument.
   *
   * @aliases cf
   */
  public function cacheflush($id = NULL) {
    if (isset($id)) {
      if (is_numeric($id)) {
        $cacheflush = cacheflush_load($id);
        if ($cacheflush && $cacheflush->getStatus() == 1) {
          $msg = CacheflushApi::create(\Drupal::getContainer())->clearPresetCache($cacheflush);
        }
        else {
          $msg = t('No entity with this id: "@variable", or entity not published yet.', ['@variable' => $id]);
        }
      }
      else {
        $msg = t('Please provide the ID of the preset (numeric value) ex: "drush cf 1".');
      }
      if (isset($msg)) {
        $this->logger->notice($msg);
      }
    }
    else {
      $this->logger->notice(t('Preset list. Use "drush cf ID" to clear cache.'));
      foreach (cacheflush_load_multiple_by_properties(['status' => 1]) as $id => $entity) {
        $msg = '[' . $id . ']   :   ' . $entity->getTitle();
        $this->logger->notice($msg);
      }
    }
  }

}
