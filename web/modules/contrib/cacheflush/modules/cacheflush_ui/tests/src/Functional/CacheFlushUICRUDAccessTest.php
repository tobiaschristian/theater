<?php

namespace Drupal\cacheflush_ui\Tests;

use Drupal\cacheflush\Controller\CacheflushApi;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Tests\BrowserTestBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Test cacheflush UI access on links and interface.
 *
 * @group cacheflush
 */
class CacheFlushUICRUDAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['cacheflush_ui'];

  /**
   * CRUD urls.
   *
   * @var array
   */
  protected $urls = [
    'admin' => 'admin/structure/cacheflush',
    'new' => 'admin/structure/cacheflush/add',
    'clear' => 'admin/cacheflush/clear/',
    'view' => 'cacheflush/',
    'edit' => 'cacheflush/[ID]/edit',
    'delete' => 'cacheflush/[ID]/delete',
  ];

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;
  protected $loggedUser;
  protected $interfaceUser;

  /**
   * Drupal container.
   *
   * @var null|\Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * The router builder.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   *   The router builder.
   */
  protected $routerBuilder;

  /**
   * Class constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The messenger service.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $router_builder
   *   The router builder service.
   */
  public function __construct(ContainerInterface $container, RouteBuilderInterface $router_builder) {
    parent::__construct();
    $this->container = $container;
    $this->routerBuilder = $router_builder;
  }

  /**
   * Class create method.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   *
   * @return static
   *   The returned static form.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container,
      $container->get('router.builder')
    );
  }

  /**
   * Sets up the test.
   */
  public function setUp(): void {
    parent::setUp();

    $role1 = [
      'cacheflush clear cache',
      'cacheflush administer',
      'cacheflush clear any',
      'cacheflush create new',
      'cacheflush view any',
      'cacheflush edit any',
      'cacheflush delete any',
    ];
    $this->adminUser = $this->drupalCreateUser($role1);

    $role2 = [
      'cacheflush clear cache',
      'cacheflush clear own',
      'cacheflush create new',
      'cacheflush view own',
      'cacheflush edit own',
      'cacheflush delete own',
    ];
    $this->loggedUser = $this->drupalCreateUser($role2);

    $role3 = [
      'cacheflush administer',
      'cacheflush view own',
      'cacheflush edit own',
      'cacheflush delete own',
    ];
    $this->interfaceUser = $this->drupalCreateUser($role3);

    $role4 = [
      'cacheflush administer',
      'cacheflush view any',
      'cacheflush edit own',
    ];
    $this->interfaceUser2 = $this->drupalCreateUser($role4);
  }

  /**
   * Run CRUD access test functions.
   */
  public function testAccess() {
    $this->createTestEntitys();
    $this->accessAnonymous();
    $this->accessLogged();
    $this->accessAdmin();
    $this->accessInterface();
    $this->accessInterface2();
  }

  /**
   * Test the access for user with no permissions.
   */
  public function accessAnonymous() {

    // Access to administration page.
    $this->cacheflushUrlAccess($this->urls['admin'], 403);
    // Access to new entity create.
    $this->cacheflushUrlAccess($this->urls['new'], 403);
    // Access to cache clear.
    $this->cacheflushUrlAccess($this->urls['clear'] . '1', 403);
    $this->cacheflushUrlAccess($this->urls['clear'] . '2', 403);
    // Access to CRUD.
    $this->cacheflushUrlAccess($this->urls['view'] . '1', 403);
    $this->cacheflushUrlAccess(str_replace("[ID]", 1, $this->urls['edit']), 403);
    $this->cacheflushUrlAccess(str_replace("[ID]", 1, $this->urls['delete']), 403);
  }

  /**
   * Test the access for user with permissions for own content.
   */
  public function accessLogged() {

    $this->drupalLogin($this->loggedUser);

    // Access to administration page.
    $this->cacheflushUrlAccess($this->urls['admin'], 403);
    // Access to new entity create.
    $this->cacheflushUrlAccess($this->urls['new'], 200);
    // Access to cache clear.
    $this->cacheflushUrlAccess($this->urls['clear'] . '1', 200);
    $this->cacheflushUrlAccess($this->urls['clear'] . '2', 403);
    // Access to CRUD for own entity.
    $this->cacheflushUrlAccess($this->urls['view'] . '1', 200);
    $this->cacheflushUrlAccess(str_replace("[ID]", 1, $this->urls['edit']), 200);
    $this->cacheflushUrlAccess(str_replace("[ID]", 1, $this->urls['delete']), 200);
    // Access to CRUD for other user created entity.
    $this->cacheflushUrlAccess($this->urls['view'] . '2', 403);
    $this->cacheflushUrlAccess(str_replace("[ID]", 2, $this->urls['edit']), 403);
    $this->cacheflushUrlAccess(str_replace("[ID]", 2, $this->urls['delete']), 403);

    $this->drupalLogout();
  }

  /**
   * Test the access for user with permissions to all content (full CRUD).
   */
  public function accessAdmin() {

    $this->drupalLogin($this->adminUser);

    // Access to administration page.
    $this->cacheflushUrlAccess($this->urls['admin'], 200);
    // Access to new entity create.
    $this->cacheflushUrlAccess($this->urls['new'], 200);
    // Access to cache clear.
    $this->cacheflushUrlAccess($this->urls['clear'] . '1', 200);
    $this->cacheflushUrlAccess($this->urls['clear'] . '2', 200);
    // Access to CRUD for own entity.
    $this->cacheflushUrlAccess($this->urls['view'] . '2', 200);
    $this->cacheflushUrlAccess(str_replace("[ID]", 2, $this->urls['edit']), 200);
    $this->cacheflushUrlAccess(str_replace("[ID]", 2, $this->urls['delete']), 200);
    // Access to CRUD for other user created entity.
    $this->cacheflushUrlAccess($this->urls['view'] . '1', 200);
    $this->cacheflushUrlAccess(str_replace("[ID]", 1, $this->urls['edit']), 200);
    $this->cacheflushUrlAccess(str_replace("[ID]", 1, $this->urls['delete']), 200);

    // Check Access on the list interface.
    $this->drupalGet('admin/structure/cacheflush');
    $this->assertSession()->responseContains('LoggedUserEntity');
    $this->assertSession()->responseContains('AdminUserEntity');
    $this->assertSession()->responseContains('InterfaceUserEntity');
    $this->assertSession()->responseContains('InterfaceUser2Entity');

    // User has access on the 4 entities to all operations.
    $this->assertSession()->linkExists('Edit', 3);
    $this->assertSession()->linkExists('Delete', 3);

    $this->drupalLogout();
  }

  /**
   * Test interface listed entity views access.
   */
  public function accessInterface() {

    $this->drupalLogin($this->interfaceUser);

    // Check Access on the list interface.
    $this->drupalGet('admin/structure/cacheflush');
    $this->assertSession()->responseNotContains('LoggedUserEntity');
    $this->assertSession()->responseNotContains('AdminUserEntity');
    $this->assertSession()->responseContains('InterfaceUserEntity');
    $this->assertSession()->responseNotContains('InterfaceUser2Entity');

    // User has access on the own entity to all operations.
    $this->assertSession()->linkExists('Edit', 0);
    $this->assertSession()->linkExists('Delete', 0);

    $this->drupalLogout();
  }

  /**
   * Test interface listed entity views access.
   */
  public function accessInterface2() {

    $this->drupalLogin($this->interfaceUser2);

    // Check Access on the list interface.
    $this->drupalGet('admin/structure/cacheflush');
    $this->assertSession()->responseContains('LoggedUserEntity');
    $this->assertSession()->responseContains('AdminUserEntity');
    $this->assertSession()->responseContains('InterfaceUserEntity');
    $this->assertSession()->responseContains('InterfaceUser2Entity');

    // User has access to all entities.
    // Edit 1 -> own.
    // No delete.
    $this->assertSession()->linkExists('Edit', 0);
    $this->assertSession()->linkNotExists('Delete');

    $this->drupalLogout();
  }

  /**
   * Check access of URL.
   *
   * @param string $url
   *   URL to check access.
   * @param int $code
   *   HTTP response.
   */
  public function cacheflushUrlAccess($url, $code) {
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals($code);
  }

  /**
   * Create cacheflush test entities.
   */
  public function createTestEntitys() {
    $options = CacheflushApi::create($this->container)->getOptionList();

    $data = [];
    $data['bootstrap']['functions'] = $options['bootstrap']['functions'];
    $data['config']['functions'] = $options['config']['functions'];
    $data['default']['functions'] = $options['default']['functions'];
    $data = serialize($data);

    $entity = cacheflush_create([
      'title' => 'LoggedUserEntity',
      'status' => 1,
      'menu' => 1,
      'data' => $data,
      'uid' => $this->loggedUser->id(),
    ]);
    $entity->save();

    $entity = cacheflush_create([
      'title' => 'AdminUserEntity',
      'status' => 1,
      'menu' => 1,
      'data' => $data,
      'uid' => $this->adminUser->id(),
    ]);
    $entity->save();

    $entity = cacheflush_create([
      'title' => 'InterfaceUserEntity',
      'status' => 1,
      'menu' => 1,
      'data' => $data,
      'uid' => $this->interfaceUser->id(),
    ]);
    $entity->save();

    $entity = cacheflush_create([
      'title' => 'InterfaceUser2Entity',
      'status' => 1,
      'menu' => 1,
      'data' => $data,
      'uid' => $this->interfaceUser2->id(),
    ]);
    $entity->save();

    $this->routerBuilder->rebuild();
  }

}
