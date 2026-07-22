<?php

namespace Drupal\Tests\cacheflush\Functional;

use Drupal\cacheflush\Controller\CacheflushApi;
use Drupal\Tests\BrowserTestBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Test cacheflush API.
 *
 * @group cacheflush
 */
class CacheFlushTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * User of test.
   *
   * @var \Drupal\Core\Session\AccountInterface|bool
   */
  protected $testUser;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['cacheflush'];

  /**
   * Drupal container.
   *
   * @var null|\Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * CacheflushApi constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The messenger service.
   */
  public function __construct(ContainerInterface $container) {
    parent::__construct();
    $this->container = $container;
  }

  /**
   * Sets up the test.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->testUser = $this->drupalCreateUser(['cacheflush clear cache']);
  }

  /**
   * Run test functions.
   */
  public function testMenu() {
    $this->menuAccessAnonymusUser();
    $this->clearPresetMenu();
  }

  /**
   * Check menus access.
   */
  public function menuAccessAnonymusUser() {
    // Check access of the menus - access denied expected - Anonymus user.
    $this->drupalGet('admin/cacheflush');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('admin/cacheflush/clear/all');
    $this->assertSession()->statusCodeEquals(403);

    // No entity created yet, the route will try to load entity - 404 will be
    // returned by Entity Manager.
    $this->drupalGet('admin/cacheflush/clear/1');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Check clear cache.
   */
  public function clearPresetMenu() {

    $this->createTestEntitys();

    $enabled = array_values(cacheflush_load_multiple_by_properties([
      'title' => 'Enabled',
      'status' => 1,
    ]));
    $this->assertEquals($enabled[0]->title->value, 'Enabled', 'Created and loaded entity: enabled.');
    $disabled = array_values(cacheflush_load_multiple_by_properties([
      'title' => 'Disabled',
      'status' => 0,
    ]));
    $this->assertEquals($disabled[0]->title->value, 'Disabled', 'Created and loaded entity: disabled.');

    $this->drupalLogin($this->testUser);

    // Check access of the menus - access TRUE expected.
    $this->drupalGet('admin/cacheflush');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet('admin/cacheflush/clear/all');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet('admin/cacheflush/clear/' . $enabled[0]->id->value);
    $this->assertSession()->statusCodeEquals(200);

    // Check if the disabled entity will be refused.
    $this->drupalGet('admin/cacheflush/clear/' . $disabled[0]->id->value);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogout();
  }

  /**
   * Create cacheflush test entities.
   */
  public function createTestEntitys() {
    $data = [];
    foreach (CacheflushApi::create($this->container)
      ->getOptionList() as $key => $value) {
      $data[$key]['functions'] = $value['functions'];
    }
    $data = serialize($data);

    $entity = cacheflush_create([
      'title' => 'Enabled',
      'status' => 1,
      'data' => $data,
    ]);
    $entity->save();
    $entity = cacheflush_create([
      'title' => 'Disabled',
      'status' => 0,
      'data' => $data,
    ]);
    $entity->save();
  }

}
