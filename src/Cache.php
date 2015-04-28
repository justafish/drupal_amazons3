<?php

namespace Drupal\amazons3;

use Capgemini\Cache\DrupalDoctrineCache;

/**
 * Cache configured to cache in the cache_amazons3_metadata bin.
 *
 * @class Cache
 * @package Drupal\amazons3
 */
class Cache extends DrupalDoctrineCache {

  /**
   * @const Represent a permanent cache item.
   */
  const CACHE_PERMANENT = 0;

  /**
   * Override __construct() to set the cache bin.
   */
  function __construct() {
    $this->setCacheTable('cache_amazons3_metadata');
  }
}
