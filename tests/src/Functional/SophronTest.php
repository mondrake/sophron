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
  protected $parser;

  public static $modules = ['sophron'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->parser = $this->container->get('uaparser');
    $this->drupalLogin($this->drupalCreateUser([
      'administer site configuration',
    ]));
  }

  /**
   * Test settings form.
   */
  public function testFormAndSettings() {
    $config = \Drupal::config('sophron.settings');

    // The default map has been set by install.
    $this->assertEquals('Drupal\sophron\Map\DrupalMap', $config->get('map_class'));

    // Loading the form, the regexes.php file is created.
    $this->drupalGet($this->sophronAdmin);
    $edit = [
      'map_class' => 'FileEye\MimeMap\Map\DefaultMap',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save configuration');

    // FileEye map has been set as default.
    $this->assertEquals('FileEye\MimeMap\Map\DefaultMap', $config->get('map_class'));
  }

}
