<?php

namespace Drupal\amazons3Test\DrupalAdapter;

/**
 * Methods that map to includes/bootstrap.inc.
 *
 * @class Bootstrap
 * @package Drupal\amazons3\DrupalAdapter
 */
trait Bootstrap {

  /**
   * Static version of variable_get() for testing.
   *
   * @param string $name
   * @param null $default
   * @return string
   */
  public static function variable_get($name, $default = NULL) {
    switch ($name) {
      case 'https':
        return FALSE;
    }

    if ($default) {
      return $default;
    }

    return 'placeholder';
  }
}
