<?php

namespace Drupal\amazons3\DrupalAdapter;

/**
 * @class Module
 * @package Drupal\amazons3\DrupalAdapter
 * @codeCoverageIgnore
 */
trait Module {

  /**
   * @see module_invoke_all()
   * @param string $hook
   * @return array
   */
  public function module_invoke_all($hook) {
    $args = func_get_args();
    return call_user_func_array('\module_invoke_all', $args);
  }

  /**
   * @see drupal_alter()
   * @param $type
   * @param $data
   * @param null $context1
   * @param null $context2
   * @param null $context3
   */
  public function drupal_alter($type, &$data, &$context1 = NULL, &$context2 = NULL, &$context3 = NULL) {
    \drupal_alter($type, $data, $context1, $context2, $context3);
  }
}
