<?php

namespace Drupal\Tests\sophron\Kernel;

use Drupal\KernelTests\KernelTestBase;
use FileEye\MimeMap\MappingException;

/**
 * Tests for Sophron API.
 *
 * @coversDefaultClass \Drupal\sophron\MimeMapManager
 *
 * @group sophron
 */
class SophronApiTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['sophron'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->installConfig(['sophron']);
  }

  /**
   * @covers ::getMapClass
   * @covers ::setMapClass
   * @covers ::getExtension
   */
  public function testMapGetSet() {
    $manager = \Drupal::service('sophron.mime_map.manager');
    $this->assertEquals('Drupal\sophron\Map\DrupalMap', $manager->getMapClass());
    $this->assertEquals('application/atomserv+xml', $manager->getExtension('atomsrv')->getDefaultType());
    $manager->setMapClass('FileEye\MimeMap\Map\DefaultMap');
    $this->assertEquals('application/octet-stream', $manager->getExtension('atomsrv')->getDefaultType(FALSE));
    $this->setExpectedException(MappingException::class);
    $manager->getExtension('atomsrv')->getDefaultType();
  }

  /**
   * @covers ::getMapClass
   * @covers ::getMappingErrors
   */
  public function testGetMappingErrors() {
    if (PHP_VERSION_ID < 70000) {
      $this->markTestSkipped('Not supported before PHP 7.0');
    }
    $config = \Drupal::configFactory()->getEditable('sophron.settings');
    $config
      ->set('map_class', 'FileEye\MimeMap\Map\DefaultMap')
      ->set('map_commands', [
        ['aaa', ['paramA', 'paramB']],
        ['bbb', ['paramC', 'paramD']],
        ['ccc', ['paramE']],
        ['ddd', []],
      ])
      ->save();
    $manager = \Drupal::service('sophron.mime_map.manager');
    $this->assertEquals('FileEye\MimeMap\Map\DefaultMap', $manager->getMapClass());
    $this->assertCount(4, $manager->getMappingErrors('FileEye\MimeMap\Map\DefaultMap'));
  }

}
