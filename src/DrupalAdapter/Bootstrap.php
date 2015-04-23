<?php

namespace Drupal\amazons3\DrupalAdapter;

/**
 * Methods that map to includes/bootstrap.inc.
 *
 * @class Bootstrap
 * @package Drupal\amazons3\DrupalAdapter
 * @codeCoverageIgnore
 */
trait Bootstrap {

  /**
   * @param $name
   * @param null $default
   * @return null
   */
  public static function variable_get($name, $default = NULL) {
    return variable_get($name, $default);
  }
}
