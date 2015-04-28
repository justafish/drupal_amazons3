<?php

namespace Drupal\amazons3Test;

use Drupal\amazons3\Cache;

/**
 * Test our cache wrapper.
 *
 * @class CacheTest
 * @package Drupal\amazons3Test
 */
class CacheTest extends \PHPUnit_Framework_TestCase {

  /**
   * Test that the cache bin is properly set.
   *
   * @covers Drupal\amazons3\cache::__construct
   */
  public function testCacheBin() {
    $cache = new Cache();
    $this->assertEquals('cache_amazons3_metadata', $cache->getCacheTable());
  }
}
