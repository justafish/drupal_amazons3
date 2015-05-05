<?php

namespace Drupal\amazons3Test\DrupalAdapter;

/**
 * Methods that map to includes/bootstrap.inc.
 *
 * @class Bootstrap
 * @package Drupal\amazons3\DrupalAdapter
 * @codeCoverageIgnore
 */
trait Bootstrap {

  protected static $variableData = array();

  public static function setVariableData(array $data) {
    static::$variableData = $data;
  }

  /**
   * Static version of variable_get() for testing.
   *
   * @param string $name
   * @param null $default
   * @return string
   */
  public static function variable_get($name, $default = NULL) {
    if (isset(static::$variableData[$name])) {
      return static::$variableData[$name];
    }

    if (isset($default)) {
      return $default;
    }
  }
}
