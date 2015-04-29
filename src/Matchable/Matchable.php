<?php

namespace Drupal\amazons3\Matchable;

/**
 * Interface for objects that can be matched against a given string.
 *
 * @package Drupal\amazons3\Matchable
 */
interface Matchable {

  /**
   * Match this object against a string.
   *
   * Typically, this will be done with a basic string contains, glob, or regular
   * expression.
   *
   * @param string $subject
   *   The string to match against.
   *
   * @return Matchable|bool
   *   The object that matched, or FALSE if no match was found.
   */
  public function match($subject);
}
