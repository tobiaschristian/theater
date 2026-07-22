<?php

namespace Drupal\Tests\cacheflush_cron\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test cacheflush Cron UI.
 *
 * @group cacheflush
 */
class CacheFlushCronUI extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['cacheflush_ui', 'cacheflush_cron'];

  /**
   * User roles.
   *
   * @var array
   */
  public static $roles = [
    'cacheflush create new',
    'cacheflush administer',
    'cacheflush view own',
    'cacheflush edit own',
    'cacheflush delete own',
  ];

  /**
   * Sets up the test.
   */
  public function setUp(): void {
    parent::setUp();
    $user = $this->drupalCreateUser(self::$roles);
    $this->drupalLogin($user);
  }

  /**
   * Add form test.
   */
  public function addForm() {
    $this->drupalGet('admin/structure/cacheflush/add');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('cron');
    $this->assertSession()->responseContains(t('Enable cron job for this preset.'));
    $this->assertSession()->linkNotExists(t('EDIT'));
  }

  /**
   * Edit form test.
   */
  public function editForm() {
    $this->drupalGet('admin/structure/cacheflush/add');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains(t('Enable cron job for this preset.'));

    // Test Entity create.
    $data = [
      'title' => 'NewEntityTitle',
      'cron' => 1,
    ];
    $this->drupalGet('admin/structure/cacheflush/add');
    $this->submitForm($data, t('Save'));
    $entities = array_values(cacheflush_load_multiple_by_properties(['title' => 'NewEntityTitle']));

    $this->assertEquals($entities[0]->getTitle(), 'NewEntityTitle', 'Entity successfully created.');

    $this->drupalGet('cacheflush/' . $entities[0]->id() . '/edit');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('cron', 1);
    $this->assertSession()->linkExists(t('Edit'));

    $data['cron'] = 0;
    $this->drupalGet('cacheflush/' . $entities[0]->id() . '/edit');
    $this->submitForm($data, t('Save'));
    $entities = array_values(cacheflush_load_multiple_by_properties(['title' => 'NewEntityTitle']));

    $this->drupalGet('cacheflush/' . $entities[0]->id() . '/edit');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('cron', 0);

    // Test Entity create.
    $data = [
      'title' => 'NewEntityTitle2',
    ];
    $this->drupalGet('admin/structure/cacheflush/add');

    // Test that the Edit link should not be in UI if no CronJob created yet.
    $this->submitForm($data, t('Save'));
    $entities = array_values(cacheflush_load_multiple_by_properties(['title' => 'NewEntityTitle2']));
    $this->assertEquals($entities[0]->getTitle(), 'NewEntityTitle2', 'Entity successfully created.');
    $this->drupalGet('cacheflush/' . $entities[0]->id() . '/edit');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('cron', 0);
    $this->assertSession()->linkNotExists('Edit');
  }

}
