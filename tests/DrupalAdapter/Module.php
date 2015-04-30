<?php

namespace Drupal\amazons3Test\DrupalAdapter;

/**
 * Stub for module.inc functions, with a call counter to log each call.
 *
 * @class Module
 * @package Drupal\amazons3Test\DrupalAdapter
 * @codeCoverageIgnore
 */
trait Module {

  protected $callCount = array();

  /**
   * @see module_invoke_all()
   * @param string $hook
   * @return array
   */
  public function module_invoke_all($hook) {
    $this->logCall(__FUNCTION__ . ':' . $hook);
    return array();
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
    $this->logCall(__FUNCTION__ . ':' . $type);
  }

  /**
   * Log a function call.
   *
   * @param string $key
   *   A string with a function and any context data.
   */
  protected function logCall($key) {
    if (!isset($this->callCount[$key])) {
      $this->callCount[$key] = 0;
    }
    $this->callCount[$key]++;
  }

  /**
   * Get the call count for a given key.
   *
   * @param string $key
   *
   * @return int
   *   The number of times $key has been called.
   */
  public function getCallCount($key) {
    if (isset($this->callCount[$key])) {
      return $this->callCount[$key];
    }

    return 0;
  }
}
