<?php

namespace Drupal\amazons3Test\Matchable;

use Drupal\amazons3\Matchable\BasicPath;
use Drupal\amazons3\Matchable\MatchablePaths;

class MatchablePathsTest extends \PHPUnit_Framework_TestCase {

  /**
   * @covers Drupal\amazons3\Matchable\MatchablePaths::__construct
   * @covers Drupal\amazons3\Matchable\MatchablePaths::__toString
   */
  public function testImplode() {
    $mp = new MatchablePaths(BasicPath::factory(array('.*', '.?')));
    $this->assertEquals('.*|.?', (string) $mp);
  }

  /**
   * @covers Drupal\amazons3\Matchable\MatchablePaths::match
   */
  public function testMatch() {
    $paths = BasicPath::factory(array('foo', 'bar'));
    $mp = new MatchablePaths($paths);
    $this->assertSame($paths[0], $mp->match('foo'));
    $this->assertSame($paths[1], $mp->match('bar'));
  }

  /**
   * @covers Drupal\amazons3\Matchable\MatchablePaths::match
   */
  public function testNoMatch() {
    $paths = BasicPath::factory(array('foo', 'bar'));
    $mp = new MatchablePaths($paths);
    $this->assertFalse($mp->match('no-match'));
  }
}
