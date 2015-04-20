<?php

namespace Drupal\amazons3Test;

use Aws\Common\Credentials\Credentials;
use Aws\S3\S3Client;
use Drupal\amazons3\StreamWrapper;
use Drupal\amazons3\StreamWrapperConfiguration;

/**
 * Tests \Drupal\amazons3\StreamWrapper.
 *
 * @class StreamWrapperTest
 * @package Drupal\amazons3
 */
class StreamWrapperTest extends \PHPUnit_Framework_TestCase {

  /**
   * @var StreamWrapper
   */
  protected $wrapper;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    require_once __DIR__ . '/include/DrupalStreamWrapperInterface.inc';

    $config = StreamWrapperConfiguration::fromConfig([
      'bucket' => 'bucket.example.com',
      'caching' => FALSE,
    ]);
    StreamWrapper::setClient(
      S3Client::factory([
      'credentials' => new Credentials('placeholder', 'placeholder'),
    ]));
    $this->wrapper = new StreamWrapper($config);
  }

  /**
   * Test that a null dirname returns the bucket associated with the wrapper.
   */
  public function testDirnameNull() {
    $this->assertEquals('s3://bucket.example.com', $this->wrapper->dirname());
  }

  /**
   * Test that we can fetch the dirname from a different bucket.
   */
  public function testDirnameBucket() {
    $this->assertEquals('s3://bucket.different.com', $this->wrapper->dirname('s3://bucket.different.com'));
  }

  /**
   * Test that dirname works with a key.
   */
  public function testDirnameSubdir() {
    $this->assertEquals('s3://bucket.example.com', $this->wrapper->dirname('s3://bucket.example.com/subdir'));
  }

  /**
   * Test that dirname works with a pseudo directory.
   */
  public function testDirnameNested() {
    $this->assertEquals('s3://bucket.example.com/subdir', $this->wrapper->dirname('s3://bucket.example.com/subdir/second-subdir'));
  }

  /**
   * Test that dirname properly handles trailing slashes.
   */
  public function testDirnameTrailingSlash() {
    $this->assertEquals('s3://bucket.example.com/subdir/second-subdir', $this->wrapper->dirname('s3://bucket.example.com/subdir/second-subdir/'));
  }
}
