<?php

namespace Drupal\amazons3Test;

use Drupal\amazons3Test\Stub\CompositeFactory;

/**
 * Test CompositeFactory.
 *
 * @class CompositeFactoryTest
 * @package Drupal\amazons3Test
 */
class CompositeFactoryTest extends \PHPUnit_Framework_TestCase {

  /**
   * @covers Drupal\amazons3\CompositeFactory::factory
   */
  public function testHooks() {
    $c = new CompositeFactory();
    $c->factory('null-command');
    $this->assertEquals($c->getCallCount('module_invoke_all:amazons3_command_prepare'), 1);
    $this->assertEquals($c->getCallCount('drupal_alter:amazons3_command_prepare'), 1);
    $this->assertEquals($c->getCallCount('drupal_alter:amazons3_command'), 1);
  }
}
