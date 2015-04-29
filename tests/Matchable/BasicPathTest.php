<?php

namespace Drupal\amazons3Test\Matchable;

use Drupal\amazons3\Matchable\BasicPath;

/**
 * @class BasicPathTest
 * @package Drupal\amazons3Test\Matchable
 */
class BasicPathTest extends \PHPUnit_Framework_TestCase {

  /**
   * @covers Drupal\amazons3\Matchable\BasicPath::factory
   * @covers Drupal\amazons3\Matchable\BasicPath::__construct
   * @covers Drupal\amazons3\Matchable\BasicPath::getPath
   */
  public function testFactory() {
    $paths = BasicPath::factory(array('.*', '.?'));
    $this->assertTrue(is_array($paths));
    foreach ($paths as $path) {
      $this->assertInstanceOf('Drupal\amazons3\Matchable\BasicPath', $path);
    }
    $this->assertEquals('.*', $paths[0]->getPath());
    $this->assertEquals('.?', $paths[1]->getPath());
  }

  /**
   * @covers Drupal\amazons3\Matchable\BasicPath::__toString
   */
  public function testToString() {
    $path = new BasicPath('.*');
    $this->assertEquals($path->getPath(), (string) $path);
  }

  /**
   * @covers Drupal\amazons3\Matchable\BasicPath::__construct
   * @expectedException \InvalidArgumentException
   */
  public function testInvalidPattern() {
    new BasicPath('?');
  }

  /**
   * @covers Drupal\amazons3\Matchable\BasicPath::__construct
   */
  public function testStarPattern() {
    $path = new BasicPath('*');
    $this->assertSame($path, $path->match('foo'));
  }

  /**
   * @covers Drupal\amazons3\Matchable\MatchableRegex::match
   */
  public function testRegexMatching() {
    $path = new BasicPath('^ab$');
    $this->assertSame($path, $path->match('ab'));
    $this->assertFalse($path->match('yz'));
  }
}
