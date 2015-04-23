<?php

namespace Drupal\amazons3Test;

use Drupal\amazons3\S3Url;

/**
 * Test S3Url.
 *
 * @class S3UrlTest
 * @package Drupal\amazons3Test
 */
class S3UrlTest extends \PHPUnit_Framework_TestCase {

  /**
   * Test that we set the scheme, bucket, and key properly.
   *
   * @covers Drupal\amazons3\S3Url::__construct
   */
  public function testConstruct() {
    $url = new S3Url('bucket', 'key');
    $this->assertEquals('s3://bucket/key', (string) $url);
    $this->assertEquals('bucket', $url->getBucket());
    $this->assertEquals('key', $url->getKey());
  }

  /**
   * @covers Drupal\amazons3\S3Url::setBucket
   * @covers Drupal\amazons3\S3Url::getBucket
   */
  public function testGetBucket() {
    $url = new S3Url('bucket');
    $url->setBucket('second-bucket');
    $this->assertEquals('second-bucket', $url->getBucket());
  }

  /**
   * @covers Drupal\amazons3\S3Url::setKey
   * @covers Drupal\amazons3\S3Url::getKey
   */
  public function testGetKey() {
    $url = new S3Url('bucket');
    $url->setKey('key');
    $this->assertEquals('key', $url->getKey());
  }

  /**
   * @covers Drupal\amazons3\S3Url::setPath
   */
  public function testSetPath() {
    $url = new S3Url('bucket');
    $url->setPath(array('directory', 'key'));
    $this->assertEquals('/directory/key', $url->getPath());
  }

  /**
   * @covers Drupal\amazons3\S3Url::getImageStyleUrl
   */
  public function testGetImageStyleUrl() {
    $url = new S3Url('bucket', 'key');
    $styleUrl = $url->getImageStyleUrl('style_name');
    $this->assertEquals($styleUrl->getKey(), "styles/style_name/key");
  }

  /**
   * @covers Drupal\amazons3\S3Url::factory
   */
  public function testFactory() {
    $url = S3Url::factory('s3://bucket/key');
    $this->assertInstanceOf('Drupal\amazons3\S3Url', $url);
  }

  /**
   * @expectedException \InvalidArgumentException
   * @covers Drupal\amazons3\S3Url::factory
   */
  public function testFactoryInvalidUrl() {
    $url = S3Url::factory(':');
  }
}
