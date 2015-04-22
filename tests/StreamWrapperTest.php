<?php

namespace Drupal\amazons3Test;

use Aws\Common\Credentials\Credentials;
use Aws\S3\S3Client;
use Drupal\amazons3Test\Stub\StreamWrapper;
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

    StreamWrapper::setS3ClientClass('Drupal\amazons3Test\Stub\S3Client');

    $config = StreamWrapperConfiguration::fromConfig([
      'bucket' => 'bucket.example.com',
      'caching' => FALSE,
      'expiration' => 0,
    ]);
    StreamWrapper::setDefaultConfig($config);
    StreamWrapper::setClient(
      S3Client::factory([
      'credentials' => new Credentials('placeholder', 'placeholder'),
    ]));
    $this->wrapper = new StreamWrapper($config);

    if (in_array('s3', stream_get_wrappers())) {
      stream_wrapper_unregister('s3');
    }
    stream_wrapper_register('s3', '\Drupal\amazons3\StreamWrapper', STREAM_IS_URL);
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
    StreamWrapper::detachCache();
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
   * Test setting a URI.
   *
   * @covers \Drupal\amazons3\StreamWrapper::setUri
   * @covers \Drupal\amazons3\StreamWrapper::getUri
   */
  public function testSetUri() {
    $wrapper = new StreamWrapper();
    $uri = 's3://bucket.example.com/key';
    $wrapper->setUri($uri);
    $this->assertEquals($uri, $wrapper->getUri());
  }

  /**
   * Test setting a scheme-only URI.
   *
   * @covers \Drupal\amazons3\StreamWrapper::setUri
   */
  public function testSetSchemeUri() {
    $wrapper = new StreamWrapper();
    $wrapper->setUri('s3://');
    $this->assertEquals('s3://bucket.example.com', $wrapper->getUri());
  }

  /**
   * Test that we throw an exception if a URI is not set.
   *
   * @expectedException \LogicException
   * @covers \Drupal\amazons3\StreamWrapper::getExternalUrl
   */
  public function testExternalUriNotSet() {
    $wrapper = new StreamWrapper();
    $wrapper->getExternalUrl();
  }

  /**
   * Test when an image doesn't exist that we return the internal style URL.
   *
   * @covers \Drupal\amazons3\StreamWrapper::getExternalUrl
   */
  public function testExternalImageStyleUri() {
    $wrapper = new StreamWrapper();
    $wrapper->setUri('s3://bucket.example.com/styles/thumbnail/image.jpg');
    $this->assertEquals('http://amazons3.example.com/' . StreamWrapper::stylesCallback . '/bucket.example.com/styles/thumbnail/image.jpg', $wrapper->getExternalUrl());
  }

  /**
   * Test regular URL generation.
   *
   * @covers \Drupal\amazons3\StreamWrapper::getExternalUrl
   */
  public function testExternalUri() {
    $wrapper = new StreamWrapper();
    $wrapper->setUri('s3://bucket.example.com/image.jpg');
    $this->assertEquals('https://s3.amazonaws.com/bucket.example.com/image.jpg', $wrapper->getExternalUrl());
  }

  /**
   * Test getting a mime type.
   *
   * @covers \Drupal\amazons3\StreamWrapper::getMimeType
   */
  public function testGetMimeType() {
    $mimeType = StreamWrapper::getMimeType('s3://bucket.example.com/image.jpg');
    $this->assertEquals('image/jpeg', $mimeType);
  }

  /**
   * Test getting the default mime type.
   *
   * @covers \Drupal\amazons3\StreamWrapper::getMimeType
   */
  public function testGetMimeTypeDefault() {
    $mimeType = StreamWrapper::getMimeType('s3://bucket.example.com/image');
    $this->assertEquals('application/octet-stream', $mimeType);
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

  /**
   * Test that stream registrations are blocked.
   *
   * @covers \Drupal\amazons3\StreamWrapper::register
   * @expectedException \LogicException
   */
  public function testRegister() {
    $client = S3Client::factory(
      [
        'credentials' => new Credentials('placeholder', 'placeholder'),
      ]
    );
    StreamWrapper::register($client);
  }
}
