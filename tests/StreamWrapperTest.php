<?php

namespace Drupal\amazons3Test;

use Aws\Common\Credentials\Credentials;
use Aws\S3\S3Client;
use Drupal\amazons3\Matchable\BasicPath;
use Drupal\amazons3\Matchable\MatchablePaths;
use Drupal\amazons3\Matchable\PresignedPath;
use Drupal\amazons3Test\Stub\S3Client as DrupalS3Client;
use Drupal\amazons3Test\Stub\StreamWrapper;
use Drupal\amazons3Test\Stub\StreamWrapperConfiguration;
use Guzzle\Http\Message\Response;
use Guzzle\Http\Url;
use Guzzle\Tests\GuzzleTestCase;

/**
 * Tests \Drupal\amazons3\StreamWrapper.
 *
 * @class StreamWrapperTest
 * @package Drupal\amazons3
 */
class StreamWrapperTest extends GuzzleTestCase {

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
    $this->assertSame($config, StreamWrapper::getDefaultConfig());

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
    try {
      $this->assertInstanceOf('Doctrine\Common\Cache\ChainCache', $cache->getValue()->getCacheObject());
    }
    catch (\Exception $e) {
    }
    StreamWrapper::detachCache();

    if (isset($e)) {
      throw $e;
    }
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

    $this->assertSame($client, StreamWrapper::getClient());
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
   * @covers \Drupal\amazons3\StreamWrapper::getLocalPath
   * @covers \Drupal\amazons3\StreamWrapper::forceDownload
   * @covers \Drupal\amazons3\StreamWrapper::getS3Url
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

  /**
   * Test that the ACL is set to public-read by default.
   *
   * @covers \Drupal\amazons3\StreamWrapper::getOptions
   */
  public function testGetOptions() {
    $wrapper = new StreamWrapper();
    $wrapper->setUri('s3://bucket.example.com');
    $this->assertArraySubset(array('ACL' => 'public-read'), $wrapper->getOptions());
  }

  /**
   * @expectedException \LogicException
   * @covers \Drupal\amazons3\StreamWrapper::getOptions
   */
  public function testGetOptionsNoUri() {
    $wrapper = new StreamWrapper();
    $wrapper->getOptions();
  }

  /**
   * Test that we can set a different S3 client class.
   *
   * @covers \Drupal\amazons3\StreamWrapper::setS3ClientClass
   */
  public function testSetS3ClientClass() {
    StreamWrapper::setS3ClientClass('Drupal\amazons3Test\Stub\S3Client');
    StreamWrapper::setClient(NULL);
    DrupalS3Client::resetCalled();
    new StreamWrapper();
    $this->assertTrue(DrupalS3Client::isCalled());
  }

  /**
   * @covers \Drupal\amazons3\StreamWrapper::getBasename
   */
  public function testBasename() {
    $config = StreamWrapperConfiguration::fromConfig([
      'bucket' => 'bucket.example.com',
      'caching' => FALSE,
      'expiration' => 0,
    ]);
    $wrapper = new StreamWrapper($config);
    $wrapper->setUri('s3://bucket.example.com/force-download/test.jpg');
    $this->assertEquals('test.jpg', $wrapper->getBasename());
  }

  /**
   * Test that we throw an exception if a URI is not set.
   *
   * @expectedException \LogicException
   *
   * @covers \Drupal\amazons3\StreamWrapper::getBasename
   */
  public function testBasenameUriNotSet() {
    $wrapper = new StreamWrapper();
    $wrapper->getBasename();
  }

  /**
   * @covers \Drupal\amazons3\StreamWrapper::getExternalUrl
   * @covers \Drupal\amazons3\StreamWrapper::getS3Url
   * @covers \Drupal\amazons3\StreamWrapper::getContentDispositionAttachment
   * @covers \Drupal\amazons3\StreamWrapper::forceDownload
   */
  public function testSaveAs() {
    $config = StreamWrapperConfiguration::fromConfig([
      'bucket' => 'bucket.example.com',
      'caching' => FALSE,
      'expiration' => 0,
      'saveAsPaths' => new MatchablePaths(BasicPath::factory(array('force-download/.*'))),
    ]);
    $wrapper = new StreamWrapper($config);
    $wrapper->setUri('s3://bucket.example.com/force-download/test.jpg');
    $this->assertRegExp('!.*response-content-disposition=attachment%3B%20filename%3D%22test\.jpg.*!', $wrapper->getExternalUrl());
  }

  /**
   * @covers \Drupal\amazons3\StreamWrapper::getExternalUrl
   */
  public function testSaveAsExcluded() {
    $config = StreamWrapperConfiguration::fromConfig([
      'bucket' => 'bucket.example.com',
      'caching' => FALSE,
      'expiration' => 0,
      'saveAsPaths' => new MatchablePaths(BasicPath::factory(array('force-download/*'))),
    ]);
    $wrapper = new StreamWrapper($config);

    $wrapper->setUri('s3://bucket.example.com/test.jpg');
    $this->assertEquals('https://s3.amazonaws.com/bucket.example.com/test.jpg', $wrapper->getExternalUrl());
  }

  /**
   * @covers \Drupal\amazons3\StreamWrapper::getExternalUrl
   * @covers \Drupal\amazons3\StreamWrapper::getContentDispositionAttachment
   * @covers \Drupal\amazons3\StreamWrapper::forceDownload
   * @covers \Drupal\amazons3\StreamWrapper::getS3Url
   */
  public function testSaveAsAll() {
    $config = StreamWrapperConfiguration::fromConfig([
      'bucket' => 'bucket.example.com',
      'caching' => FALSE,
      'expiration' => 0,
      'saveAsPaths' => new MatchablePaths(BasicPath::factory(array('*'))),
    ]);
    $wrapper = new StreamWrapper($config);

    $wrapper->setUri('s3://bucket.example.com/test.jpg');
    $this->assertRegExp('!.*response-content-disposition=attachment%3B%20filename%3D%22test\.jpg.*!', $wrapper->getExternalUrl());
  }

  /**
   * Test that we properly encode filenames according to RFC2047.
   *
   * @covers \Drupal\amazons3\StreamWrapper::getExternalUrl
   * @covers \Drupal\amazons3\StreamWrapper::getContentDispositionAttachment
   */
  public function testAttachmentSpace() {
    $config = StreamWrapperConfiguration::fromConfig([
      'bucket' => 'bucket.example.com',
      'caching' => FALSE,
      'expiration' => 0,
      'saveAsPaths' => new MatchablePaths(BasicPath::factory(array('force-download/.*'))),
    ]);
    $wrapper = new StreamWrapper($config);
    $wrapper->setUri('s3://bucket.example.com/force-download/test with spaces.jpg');
    // https://s3.amazonaws.com/bucket.example.com/force-download/test%20with%20spaces.jpg?response-content-disposition=attachment%3B%20filename%3D%22test%20with%20spaces.jpg%22&AWSAccessKeyId=placeholder&Expires=1429987166&Signature=xEZpLFLnNAgIFbRuoP7VRbNUF%2BQ%3D
    $this->assertRegExp('!.*response-content-disposition=attachment%3B%20filename%3D%22test%20with%20spaces\.jpg.*!', $wrapper->getExternalUrl());
  }

  /**
   * Test that we can create torrent URLs.
   *
   * @covers \Drupal\amazons3\StreamWrapper::getExternalUrl
   * @covers \Drupal\amazons3\StreamWrapper::useTorrent
   * @covers \Drupal\amazons3\StreamWrapper::getS3Url
   */
  public function testTorrentPath() {
    $config = StreamWrapperConfiguration::fromConfig([
      'bucket' => 'bucket.example.com',
      'caching' => FALSE,
      'expiration' => 0,
      'torrentPaths' => new MatchablePaths(BasicPath::factory(array('torrents/.*'))),
    ]);

    $wrapper = new StreamWrapper($config);
    $wrapper->setUri('s3://bucket.example.com/torrents/test');
    $this->assertEquals('https://s3.amazonaws.com/bucket.example.com/torrents/test%3Ftorrent', $wrapper->getExternalUrl());
  }

  /**
   * @covers \Drupal\amazons3\StreamWrapper::getExternalUrl
   * @covers \Drupal\amazons3\StreamWrapper::usePresigned
   * @covers \Drupal\amazons3\StreamWrapper::getS3Url
   */
  public function testPresignedPath() {
    $config = StreamWrapperConfiguration::fromConfig([
      'bucket' => 'bucket.example.com',
      'caching' => FALSE,
      'expiration' => 0,
      'presignedPaths' => new MatchablePaths(PresignedPath::factory(array('presigned/.*' => 30))),
    ]);

    $wrapper = new StreamWrapper($config);
    $wrapper->setUri('s3://bucket.example.com/presigned/test');
    $url = Url::factory($wrapper->getExternalUrl());

    $this->assertNotNull($url->getQuery()->get('AWSAccessKeyId'));
    $this->assertNotNull($url->getQuery()->get('Signature'));
    $this->assertGreaterThanOrEqual(time(), $url->getQuery()->get('Expires'));

    // We allow a bit of fuzziness in the expiry to cover a different call to
    // time() in getExternalUrl().
    $this->assertLessThanOrEqual(time() + 32, $url->getQuery()->get('Expires'));
  }

  /**
   * @covers \Drupal\amazons3\StreamWrapper::getExternalUrl
   * @covers \Drupal\amazons3\StreamWrapper::injectCname
   * @covers \Drupal\amazons3\StreamWrapper::getS3Url
   */
  public function testCustomDomain() {
    $config = StreamWrapperConfiguration::fromConfig([
      'bucket' => 'bucket.example.com',
      'domain' => 'static.example.com',
      'caching' => FALSE,
      'expiration' => 0,
    ]);
    $wrapper = new StreamWrapper($config);
    $wrapper->setUri('s3://bucket.example.com/image.jpg');
    $url = Url::factory($wrapper->getExternalUrl());
    $this->assertEquals('static.example.com', $url->getHost());
  }

  /**
   * @covers \Drupal\amazons3\StreamWrapper::getExternalUrl
   * @covers \Drupal\amazons3\StreamWrapper::injectCname
   */
  public function testNoCustomDomain() {
    $config = StreamWrapperConfiguration::fromConfig([
      'bucket' => 'bucket.example.com',
      'caching' => FALSE,
    ]);
    $wrapper = new StreamWrapper($config);
    $wrapper->setUri('s3://bucket.example.com/image.jpg');
    $url = Url::factory($wrapper->getExternalUrl());
    $this->assertEquals('s3.amazonaws.com', $url->getHost());
  }

  /**
   * @covers \Drupal\amazons3\StreamWrapper::getOptions
   * @covers \Drupal\amazons3\StreamWrapper::useRrs
   */
  public function testReducedRedundancyStorage() {
    $config = StreamWrapperConfiguration::fromConfig([
      'bucket' => 'bucket.example.com',
      'caching' => FALSE,
      'reducedRedundancyPaths' => new MatchablePaths(BasicPath::factory(array('*'))),
    ]);

    $wrapper = new StreamWrapper($config);
    $wrapper->setUri('s3://bucket.example.com/styles/thumbnail/image.jpg');
    $options = $wrapper->getOptions();

    $this->assertArrayHasKey('StorageClass', $options);
    $this->assertEquals('REDUCED_REDUNDANCY', $options['StorageClass']);
  }

  /**
   * @covers \Drupal\amazons3\StreamWrapper::stream_open
   * @covers \Drupal\amazons3\StreamWrapper::mkdir
   * @covers \Drupal\amazons3\StreamWrapper::url_stat
   */
  public function testAutomaticUri() {
    $wrapper = new StreamWrapper();
    $path = NULL;
    $uri = 's3://bucket.example.com/image.jpg';
    $wrapper->stream_open($uri, 'r', 0, $path);
    $this->assertEquals($uri, $wrapper->getUri());

    // Instantiate the AWS service builder.
    $config = array (
      'includes' =>
        array (
          0 => '_aws',
        ),
      'services' =>
        array (
          'default_settings' =>
            array (
              'params' =>
                array (
                  'region' => 'us-east-1',
                ),
            ),
          'cloudfront' =>
            array (
              'extends' => 'cloudfront',
              'params' =>
                array (
                  'private_key' => 'change_me',
                  'key_pair_id' => 'change_me',
                ),
            ),
        ),
      'credentials' => array('key' => 'placeholder', 'secret' => 'placeholder'),
    );
    $aws = \Aws\Common\Aws::factory($config);

    // Configure the tests to use the instantiated AWS service builder
    \Guzzle\Tests\GuzzleTestCase::setServiceBuilder($aws);
    $client = $this->getServiceBuilder()->get('s3', true);

    // The 404 is for the first check to mkdir() that checks to see if a
    // directory exists.
    $this->setMockResponse($client, array(new Response(200), new Response(404), new Response(200)));

    $wrapper = new StreamWrapper();
    $wrapper->setClient($client);
    $wrapper->url_stat($uri, 0);
    $this->assertEquals($uri, $wrapper->getUri());

    $wrapper->mkdir('s3://bucket.example.com/directory', 0, 0);
    $this->assertEquals('s3://bucket.example.com/directory', $wrapper->getUri());
  }
}
