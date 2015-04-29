<?php

namespace Drupal\amazons3\Matchable;

/**
 * @class PresignedPath
 * @package Drupal\amazons3
 */
class PresignedPath extends BasicPath {

  /**
   * The timeout for URLs generated from this presigned path.
   *
   * @var int
   */
  protected $timeout;

  /**
   * Create an array of PresignedPaths.
   *
   * @param array $patterns
   *   An array of patterns. Each key is a regular expression, and each value is
   *   an integer of the presigned timeout in seconds.
   *
   * @return PresignedPath[]
   */
  public static function factory(array $patterns) {
    $presignedPaths = array();
    foreach ($patterns as $pattern => $timeout) {
      $presignedPaths[] = new static($pattern, $timeout);
    }

    return $presignedPaths;
  }

  /**
   * @param string $pattern
   * @param int $timeout
   */
  public function __construct($pattern, $timeout) {
    $this->pattern = $pattern;
    $this->timeout = $timeout;
  }

  /**
   * @return string
   */
  public function getPath() {
    return $this->pattern;
  }

  /**
   * @return int
   */
  public function getTimeout() {
    return $this->timeout;
  }
}
