<?php

namespace Drupal\amazons3Test\Matchable;

use Drupal\amazons3\Matchable\PresignedPath;

/**
 * @class PresignedPathTest
 * @package Drupal\amazons3Test\Matchable
 */
class PresignedPathTest extends \PHPUnit_Framework_TestCase {

  /**
   * @covers Drupal\amazons3\Matchable\PresignedPath::factory
   */
  public function testFactory() {
    $paths = PresignedPath::factory(array('.*' => 30, '.?' => 60));
    $this->assertTrue(is_array($paths));
    foreach ($paths as $path) {
      $this->assertInstanceOf('Drupal\amazons3\Matchable\PresignedPath', $path);
    }
    $this->assertEquals('.*', $paths[0]->getPath());
    $this->assertEquals(30, $paths[0]->getTimeout());
    $this->assertEquals('.?', $paths[1]->getPath());
    $this->assertEquals('60', $paths[1]->getTimeout());
  }

  /**
   * Test getters and setters on PresignedPaths.
   *
   * @covers Drupal\amazons3\Matchable\PresignedPath::__construct
   * @covers Drupal\amazons3\Matchable\PresignedPath::getPath
   * @covers Drupal\amazons3\Matchable\PresignedPath::getTimeout
   */
  public function testGetters() {
    $p = new PresignedPath('images/.*', 30);
    $this->assertEquals('images/.*', $p->getPath());
    $this->assertEquals(30, $p->getTimeout());
  }
}
