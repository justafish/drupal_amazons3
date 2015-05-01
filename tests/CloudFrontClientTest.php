<?php

namespace Drupal\amazons3Test;

use Drupal\amazons3Test\Stub\CloudFrontClient;

/**
 * @class CloudFrontClientTest
 * @package Drupal\amazons3Test
 */
class CloudFrontClientTest extends \PHPUnit_Framework_TestCase {

  /**
   * @covers Drupal\amazons3\CloudFrontClient::factory
   */
  public function testFactory() {
    $cf = CloudFrontClient::factory();
    $this->assertInstanceOf('Aws\CloudFront\CloudFrontClient', $cf);
  }
}
