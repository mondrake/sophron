<?php

namespace Drupal\Tests\sophron\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests Sophron functionality.
 *
 * @group sophron
 */
class SophronTest extends BrowserTestBase {

  protected $sophronAdmin = 'admin/config/system/sophron';

  public static $modules = ['sophron'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser([
      'administer site configuration',
    ]));
  }

  /**
   * Test settings form.
   */
  public function testFormAndSettings() {
    // The default map has been set by install.
    $this->assertEquals('Drupal\sophron\Map\DrupalMap', \Drupal::configFactory()->get('sophron.settings')->get('map_class'));

    // Load the form, and change the default map class.
    $this->drupalGet($this->sophronAdmin);
    $edit = [
      'map_class' => 'FileEye\MimeMap\Map\DefaultMap',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save configuration');

    // FileEye map has been set as default, and gaps exists.
    $this->assertSession()->responseContains('Mapping gaps');
    $this->assertEquals('FileEye\MimeMap\Map\DefaultMap', \Drupal::configFactory()->get('sophron.settings')->get('map_class'));
  }

}
