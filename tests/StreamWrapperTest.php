<?php

namespace Drupal\amazons3 {

  /**
   * Static version of variable_get() for testing.
   *
   * @param string $name
   * @param null $default
   * @return string
   */
  function variable_get($name, $default = NULL) {
    return 'placeholder';
  }

}

namespace Drupal\amazons3Test {

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
      'expiration' => 0,
    ]);
    StreamWrapper::setClient(
      S3Client::factory([
      'credentials' => new Credentials('placeholder', 'placeholder'),
    ]));
    $this->wrapper = new StreamWrapper($config);
  }

  /**
   * Test setting and getting the default configuration.
   *
   * @covers \Drupal\amazons3\StreamWrapper::setDefaultConfig
   * @covers \Drupal\amazons3\StreamWrapper::getDefaultConfig
   */
  public function testSetDefaultConfig() {
    $oldConfig = StreamWrapper::getDefaultConfig();

    $config = StreamWrapperConfiguration::fromConfig([
      'bucket' => 'bucket.example.com',
      'caching' => FALSE,
    ]);
    StreamWrapper::setDefaultConfig($config);
    $this->assertEquals($config, StreamWrapper::getDefaultConfig());

    if ($oldConfig) {
      StreamWrapper::setDefaultConfig($oldConfig);
    }
  }

  /**
   * Test that our default configuration is respected.
   *
   * @covers \Drupal\amazons3\StreamWrapper::__construct
   */
  public function testConstructDefaultConfig() {
    $config = StreamWrapperConfiguration::fromConfig([
      'bucket' => 'defaultconfig.example.com',
      'caching' => FALSE,
    ]);
    StreamWrapper::setDefaultConfig($config);
    $wrapper = new StreamWrapper();
    $wrapper->setUri('s3://');
    $this->assertEquals('s3://defaultconfig.example.com', $wrapper->getUri());
  }

  /**
   * Test that when needed the StreamWrapper will create a client.
   *
   * @covers \Drupal\amazons3\StreamWrapper::__construct
   */
  public function testCreateClient() {
    StreamWrapper::setClient(null);
    $config = StreamWrapperConfiguration::fromConfig([
      'bucket' => 'bucket.example.com',
      'caching' => FALSE,
    ]);
    $wrapper = new StreamWrapper($config);
    $this->assertNotNull($wrapper->getClient());
  }

  /**
   * Test that a Drupal cache adapter is created.
   *
   * @covers \Drupal\amazons3\StreamWrapper::__construct
   */
  public function testCreateCache() {
    $config = StreamWrapperConfiguration::fromConfig([
      'bucket' => 'bucket.example.com',
      'caching' => TRUE,
      'expiration' => 0,
    ]);

    $wrapper = new StreamWrapper($config);
    $reflect = new \ReflectionObject($wrapper);
    $cache = $reflect->getProperty('cache');
    $cache->setAccessible(TRUE);
    $this->assertInstanceOf('Capgemini\Cache\DrupalDoctrineCache', $cache->getValue()->getCacheObject());
  }

  /**
   * Test setting an S3 client.
   *
   * @covers \Drupal\amazons3\StreamWrapper::setClient
   * @covers \Drupal\amazons3\StreamWrapper::getClient
   */
  public function testSetClient() {
    $client = S3Client::factory(
      [
        'credentials' => new Credentials('placeholder', 'placeholder'),
      ]
    );

    StreamWrapper::setClient($client);

    $this->assertEquals($client, StreamWrapper::getClient());
  }

  /**
   * Test that a null dirname returns the bucket associated with the wrapper.
   *
   * @covers \Drupal\amazons3\StreamWrapper::dirname
   */
  public function testDirnameNull() {
    $this->assertEquals('s3://bucket.example.com', $this->wrapper->dirname());
  }

  /**
   * Test that we can fetch the dirname from a different bucket.
   *
   * @covers \Drupal\amazons3\StreamWrapper::dirname
   */
  public function testDirnameBucket() {
    $this->assertEquals('s3://bucket.different.com', $this->wrapper->dirname('s3://bucket.different.com'));
  }

  /**
   * Test that dirname works with a key.
   *
   * @covers \Drupal\amazons3\StreamWrapper::dirname
   */
  public function testDirnameSubdir() {
    $this->assertEquals('s3://bucket.example.com', $this->wrapper->dirname('s3://bucket.example.com/subdir'));
  }

  /**
   * Test that dirname works with a pseudo directory.
   *
   * @covers \Drupal\amazons3\StreamWrapper::dirname
   */
  public function testDirnameNested() {
    $this->assertEquals('s3://bucket.example.com/subdir', $this->wrapper->dirname('s3://bucket.example.com/subdir/second-subdir'));
  }

  /**
   * Test that dirname properly handles trailing slashes.
   *
   * @covers \Drupal\amazons3\StreamWrapper::dirname
   */
  public function testDirnameTrailingSlash() {
    $this->assertEquals('s3://bucket.example.com/subdir/second-subdir', $this->wrapper->dirname('s3://bucket.example.com/subdir/second-subdir/'));
  }
}

}
