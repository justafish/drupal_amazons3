<?php

namespace Drupal\amazons3Test\Matchable;

use Drupal\amazons3\Matchable\PresignedPath;

/**
 * @class PresignedPathTest
 * @package Drupal\amazons3Test\Matchable
 */
class PresignedPathTest extends \PHPUnit_Framework_TestCase {

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
