<?php

namespace Drupal\amazons3\Matchable;

/**
 * Base class for objects that can be matched against a regular expression.
 *
 * This class also handles '*' to mean 'any string'.
 *
 * @class MatchableRegex
 * @package Drupal\amazons3\Matchable
 */
trait MatchableRegex {

  /**
   * {@inheritdoc}
   */
  public function match($subject) {
    $pattern = $this->__toString();
    if ($pattern === '*' || preg_match('#' . strtr($pattern, '#', '\#') . '#', $subject)) {
      return $this;
    }

    return FALSE;
  }

  /**
   * Return the pattern this object matches against.
   *
   * @return string
   */
  abstract public function __toString();
}
