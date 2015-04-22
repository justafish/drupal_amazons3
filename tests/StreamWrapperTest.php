<?php

namespace Drupal\amazons3 {

  function drupal_encode_path($path) {
    return str_replace('%2F', '/', rawurlencode($path));
  }

  function url($path = NULL, array $options = array()) {
    // Merge in defaults.
    $options += array(
      'fragment' => '',
      'query' => array(),
      'absolute' => FALSE,
      'alias' => FALSE,
      'prefix' => ''
    );

    // A duplicate of the code from url_is_external() to avoid needing another
    // function call, since performance inside url() is critical.
    if (!isset($options['external'])) {
      // Return an external link if $path contains an allowed absolute URL. Avoid
      // calling drupal_strip_dangerous_protocols() if there is any slash (/),
      // hash (#) or question_mark (?) before the colon (:) occurrence - if any -
      // as this would clearly mean it is not a URL. If the path starts with 2
      // slashes then it is always considered an external URL without an explicit
      // protocol part.
      $colonpos = strpos($path, ':');
      $options['external'] = (strpos($path, '//') === 0)
        || ($colonpos !== FALSE
          && !preg_match('![/?#]!', substr($path, 0, $colonpos))
          && drupal_strip_dangerous_protocols($path) == $path);
    }

    // Preserve the original path before altering or aliasing.
    $original_path = $path;

    // Allow other modules to alter the outbound URL and options.
    // drupal_alter('url_outbound', $path, $options, $original_path);

    if (isset($options['fragment']) && $options['fragment'] !== '') {
      $options['fragment'] = '#' . $options['fragment'];
    }

    if ($options['external']) {
      // Split off the fragment.
      if (strpos($path, '#') !== FALSE) {
        list($path, $old_fragment) = explode('#', $path, 2);
        // If $options contains no fragment, take it over from the path.
        if (isset($old_fragment) && !$options['fragment']) {
          $options['fragment'] = '#' . $old_fragment;
        }
      }
      // Append the query.
      if ($options['query']) {
        $path .= (strpos($path, '?') !== FALSE ? '&' : '?') . drupal_http_build_query($options['query']);
      }
      if (isset($options['https']) && variable_get('https', FALSE)) {
        if ($options['https'] === TRUE) {
          $path = str_replace('http://', 'https://', $path);
        }
        elseif ($options['https'] === FALSE) {
          $path = str_replace('https://', 'http://', $path);
        }
      }
      // Reassemble.
      return $path . $options['fragment'];
    }

    // Strip leading slashes from internal paths to prevent them becoming external
    // URLs without protocol. /example.com should not be turned into
    // //example.com.
    $path = ltrim($path, '/');

    global $base_url, $base_secure_url, $base_insecure_url;

    // The base_url might be rewritten from the language rewrite in domain mode.
    if (!isset($options['base_url'])) {
      $options['base_url'] = 'http://amazons3.example.com';
    }

    // The special path '<front>' links to the default front page.
    if ($path == '<front>') {
      $path = '';
    }

    $base = $options['absolute'] ? $options['base_url'] . '/' : base_path();
    $prefix = empty($path) ? rtrim($options['prefix'], '/') : $options['prefix'];

    $path = drupal_encode_path($prefix . $path);
    if ($options['query']) {
      return $base . $path . '?' . drupal_http_build_query($options['query']) . $options['fragment'];
    }
    else {
      return $base . $path . $options['fragment'];
    }
  }

  /**
   * Static version of variable_get() for testing.
   *
   * @param string $name
   * @param null $default
   * @return string
   */
  function variable_get($name, $default = NULL) {
    switch ($name) {
      case 'https':
        return FALSE;
    }

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
