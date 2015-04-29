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
   * @param $pattern
   * @param $timeout
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
