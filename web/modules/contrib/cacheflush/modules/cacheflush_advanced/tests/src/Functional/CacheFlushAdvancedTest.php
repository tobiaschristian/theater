<?php

namespace Drupal\Tests\cacheflush_advanced\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test cacheflush advanced functionality.
 *
 * @group cacheflush
 */
class CacheFlushAdvancedTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to administer feeds and create content.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $User;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['cacheflush_advanced'];

  /**
   * Sets up the test.
   */
  protected function setUp(): void {
    parent::setUp();

    $roles = [
      'cacheflush create new',
      'cacheflush view own',
      'cacheflush edit own',
      'cacheflush delete own',
    ];
    $user = $this->createUser($roles);
    $this->drupalLogin($user);
    $this->interfaceErrorrs();
    $this->interfaceCrud();
  }

  /**
   * Check if errors are generated correctly.
   */
  private function interfaceErrorrs() {
    // Check interface has all fields.
    $this->drupalGet('admin/structure/cacheflush/add');
    $this->assertSession()->responseContains(t('Custom (advanced)'));
    $this->assertSession()->fieldExists('vertical_tabs_advance[cacheflush_advanced_table][0][cid]');
    $this->assertSession()->fieldExists('vertical_tabs_advance[cacheflush_advanced_table][0][table]');
    $this->assertSession()->fieldExists('advanced_remove_0');
    $this->assertSession()->fieldExists('advance_add');

    // Test NO error generate if nothing completet on the advanced form.
    $this->submitForm([], t('Save'));
    $this->assertSession()->responseNotContains(t('Cache ID is required!'));
    $this->assertSession()->responseNotContains(t('Service is required!'));

    // Test if CID sett and service not, is generating error.
    $this->submitForm(['vertical_tabs_advance[cacheflush_advanced_table][0][cid]' => 'Test 1'], t('Save'));
    $this->assertSession()->responseNotContains(t('Cache ID is required!'));
    $this->assertSession()->responseContains(t('Service is required'));

    // Test if CID not and service sett, is generating error.
    $this->submitForm([
      'vertical_tabs_advance[cacheflush_advanced_table][0][cid]' => '',
      'vertical_tabs_advance[cacheflush_advanced_table][0][table]' => 'menu',
    ], t('Save'));
    $this->assertSession()->responseContains(t('Cache ID is required!'));
    $this->assertSession()->responseNotContains(t('Service is required'));

    // Test NO error on advanced / save fail because no title sett.
    $this->submitForm([
      'vertical_tabs_advance[cacheflush_advanced_table][0][cid]' => 'TEST',
      'vertical_tabs_advance[cacheflush_advanced_table][0][table]' => 'menu',
    ], t('Save'));
    $this->assertSession()->responseNotContains(t('Cache ID is required!'));
    $this->assertSession()->responseNotContains(t('Service is required'));
    $this->assertSession()->responseContains(t('Title field is required.'));
  }

  /**
   * Test add/remove on ajax form.
   */
  private function interfaceCrud() {
    $this->drupalGet('admin/structure/cacheflush/add');

    $this->drupalPostAjaxForm(NULL, [], 'advance_add');
    $this->assertSession()->fieldExists('vertical_tabs_advance[cacheflush_advanced_table][1][cid]');
    $this->assertSession()->fieldExists('vertical_tabs_advance[cacheflush_advanced_table][1][table]');
    $this->assertSession()->fieldExists('advanced_remove_1');

    $this->drupalPostAjaxForm(NULL, [], 'advance_add');
    $this->assertSession()->fieldExists('vertical_tabs_advance[cacheflush_advanced_table][2][cid]');
    $this->assertSession()->fieldExists('vertical_tabs_advance[cacheflush_advanced_table][2][table]');
    $this->assertSession()->fieldExists('advanced_remove_2');

    $this->drupalPostAjaxForm(NULL, [], 'advance_add');
    $this->assertSession()->fieldExists('vertical_tabs_advance[cacheflush_advanced_table][3][cid]');
    $this->assertSession()->fieldExists('vertical_tabs_advance[cacheflush_advanced_table][3][table]');
    $this->assertSession()->fieldExists('advanced_remove_3');

    $this->drupalPostAjaxForm(NULL, [], 'advanced_remove_1');
    $this->assertSession()->fieldExists('advanced_remove_0');
    $this->assertSession()->fieldValueNotEquals('advanced_remove_1', '');
    $this->assertSession()->fieldExists('advanced_remove_2');

    $this->drupalPostAjaxForm(NULL, ['title' => 'Test 1'], 'advanced_remove_0');
    $this->assertSession()->fieldValueNotEquals('advanced_remove_0', '');
    $this->assertSession()->fieldValueNotEquals('advanced_remove_1', '');
    $this->assertSession()->fieldExists('advanced_remove_2');

    $this->drupalPostAjaxForm(NULL, ['title' => 'Test 1'], 'advanced_remove_2');
    $this->assertSession()->fieldValueNotEquals('advanced_remove_0', '');
    $this->assertSession()->fieldValueNotEquals('advanced_remove_1', '');
    $this->assertSession()->fieldValueNotEquals('advanced_remove_2', '');

    $this->drupalPostAjaxForm(NULL, [], 'advance_add');
    $this->assertSession()->fieldExists('advanced_remove_4');

    $this->submitForm([
      'title' => 'Test 1',
      'vertical_tabs_advance[cacheflush_advanced_table][4][cid]' => 'TEST',
      'vertical_tabs_advance[cacheflush_advanced_table][4][table]' => 'menu',
    ], t('Save'));

    $entities = array_values(cacheflush_load_multiple_by_properties(['title' => 'Test 1']));
    $this->assertEquals($entities[0]->getTitle(), 'Test 1', 'Entity successfully created.');

    // Check if entity create on interface.
    $this->drupalGet('cacheflush/' . $entities[0]->id() . '/edit');
    $this->assertSession()->fieldValueEquals('vertical_tabs_advance[cacheflush_advanced_table][4][cid]', 'TEST');
    $this->assertSession()->fieldValueEquals('vertical_tabs_advance[cacheflush_advanced_table][4][table]', 'menu');
  }

}
