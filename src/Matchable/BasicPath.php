<?php

namespace Drupal\amazons3\Matchable;

/**
 * A path pattern to use when testing URL paths.
 *
 * @class BasicPath
 * @package Drupal\amazons3\Matchable
 */
class BasicPath implements Matchable {
  use MatchableRegex;

  /**
   * The path pattern for this presigned path configuration.
   *
   * @var string
   */
  protected $pattern;

  /**
   * Create an array of BasicPaths.
   *
   * @param array $patterns
   *   An array of regular expression patterns to use when creating the basic
   *   path.
   *
   * @return BasicPath[]
   *   An array of BasicPaths.
   */
  public static function factory(array $patterns) {
    $basicPaths = array();
    foreach ($patterns as $pattern) {
      $basicPaths[] = new static($pattern);
    }

    return $basicPaths;
  }

  /**
   * Construct a new BasicPath.
   *
   * @param $pattern
   *   An regular expression pattern, without start and end markers.
   */
  public function __construct($pattern) {
    $result = @preg_match('#' . strtr($pattern, '#', '\#') . '#', 'foo');
    if ($pattern != '*' && ($result === FALSE || preg_last_error() != PREG_NO_ERROR)) {
      throw new \InvalidArgumentException('BasicPath pattern is not a valid regular expression.');
    }

    $this->pattern = $pattern;
  }

  /**
   * @return string
   */
  public function getPath() {
    return $this->pattern;
  }

  /**
   * @return string
   */
  function __toString() {
    return $this->pattern;
  }
}
