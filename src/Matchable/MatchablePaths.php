<?php

namespace Drupal\amazons3\Matchable;

/**
 * A list of paths that can be matched against a regular expression.
 *
 * @class MatchablePaths
 * @package Drupal\amazons3\Matchable
 */
class MatchablePaths implements Matchable {

  /**
   * An array of paths to match against.
   *
   * @var Matchable[]
   */
  protected $paths;

  /**
   * Construct a new set of MatchablePaths.
   *
   * @param Matchable[] $paths
   *   An array of Matchable objects.
   */
  public function __construct(array $paths = array()) {
    $this->paths = $paths;
  }

  /**
   * Return the first object that matches a subject.
   *
   * {@inheritdoc}
   */
  public function match($subject) {
    foreach ($this->paths as $path) {
      if ($path->match($subject)) {
        return $path;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return implode('|', $this->paths);
  }
}
