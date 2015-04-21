<?php

namespace Drupal\amazons3;

use Guzzle\Cache\DoctrineCacheAdapter;
use \Aws\S3\S3Client as AwsS3Client;

/**
 * @file
 * Drupal stream wrapper implementation for Amazon S3
 *
 * Implements DrupalStreamWrapperInterface to provide an Amazon S3 wrapper with
 * the s3:// prefix.
 */
class StreamWrapper extends \Aws\S3\StreamWrapper implements \DrupalStreamWrapperInterface {

  /**
   * Default configuration used when constructing a new stream wrapper.
   *
   * @var \Drupal\amazons3\StreamWrapperConfiguration
   */
  protected static $defaultConfig;

  /**
   * Configuration for this stream wrapper.
   *
   * @var \Drupal\amazons3\StreamWrapperConfiguration
   */
  protected $config;

  /**
   * Instance URI referenced as "s3://bucket/key"
   *
   * @var S3Url
   */
  protected $uri;

  /**
   * The URL associated with the S3 object.
   *
   * @var S3URL
   */
  protected $s3Url;

  /**
   * Set default configuration to use when constructing a new stream wrapper.
   *
   * @param \Drupal\amazons3\StreamWrapperConfiguration $config
   */
  public static function setDefaultConfig(StreamWrapperConfiguration $config) {
    static::$defaultConfig = $config;
  }

  /**
   * Construct a new stream wrapper.
   *
   * @param \Drupal\amazons3\StreamWrapperConfiguration $config
   *   (optional) A specific configuration to use for this wrapper.
   */
  public function __construct(StreamWrapperConfiguration $config = NULL) {
    if (!$config) {
      if (static::$defaultConfig) {
        $config = static::$defaultConfig;
      }
      else {
        $config = StreamWrapperConfiguration::fromDrupalVariables();
      }
    }

    $this->config = $config;

    if (!$this->getClient()) {
      $this->setClient(S3Client::factory());
    }

    if ($this->config->isCaching() && !static::$cache) {
      $cache = new \Capgemini\Cache\DrupalDoctrineCache();
      $cache->setCacheTable('cache_amazons3_metadata');
      static::attachCache(
        new DoctrineCacheAdapter($cache),
        $this->config->getCacheLifetime()
      );
    }
  }

  /**
   * Get the client associated with this stream wrapper.
   *
   * @return \Aws\S3\S3Client
   */
  public static function getClient() {
    return self::$client;
  }

  /**
   * Set the client associated with this stream wrapper.
   *
   * Note that all stream wrapper instances share a global client.
   *
   * @param \Aws\S3\S3Client $client
   */
  public static function setClient(AwsS3Client $client) {
    self::$client = $client;
  }

  /**
   * Support for flock().
   *
   * S3 has no support for file locking. If it's needed, it has to be
   * implemented at the application layer.
   *
   * @todo Investigate supporting stream_lock() with Drupal's lock API.
   *
   * @link https://docs.aws.amazon.com/AmazonS3/latest/API/RESTObjectPUT.html
   *
   * @param string $operation
   *   One of the following:
   *   - LOCK_SH to acquire a shared lock (reader).
   *   - LOCK_EX to acquire an exclusive lock (writer).
   *   - LOCK_UN to release a lock (shared or exclusive).
   *   - LOCK_NB if you don't want flock() to block while locking (not
   *     supported on Windows).
   *
   * @return bool
   *   returns TRUE if lock was successful
   *
   * @link http://php.net/manual/en/streamwrapper.stream-lock.php
   */
  public function stream_lock($operation) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  function setUri($uri) {
    // file_stream_wrapper_get_instance_by_scheme() assumes that all schemes
    // can work without a directory, but S3 requires a bucket. If a raw scheme
    // is passed in, we append our default bucket.
    if ($uri == 's3://') {
      $uri = 's3://' . $this->config->getBucket();
    }

    $this->uri = S3Url::factory($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function getUri() {
    return (string) $this->uri;
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalUrl() {
    if (!isset($this->uri)) {
      throw new \LogicException('A URI must be set before calling getExternalUrl().');
    }

    $local_path = $this->getLocalPath();
    $args = array(
      'Bucket' => $this->uri->getBucket(),
      'Key' => $local_path,
      'Scheme' => 'https',
    );

    // Allow other modules to change the download link type.
    // @todo Rather than passing an info array and a path, we should look into
    // replacing the commandFactory and then letting it call a hook on any S3
    // operation.
    // $args = array_merge($args, module_invoke_all('amazons3_url_info', $local_path, $args));

    // UI overrides.
    // Torrent URLs.
    // @todo Torrents are now getObjectTorrent().
    /**
    $torrent = $args['download_type'] === 'torrent' ? TRUE : FALSE;
    foreach ($this->torrents as $path) {
      if ($path === '*' || preg_match('#' . strtr($path, '#', '\#') . '#', $local_path)) {
        $args['download_type'] = 'torrent';
        break;
      }
    }**/

    // Presigned URLs.
    // @todo Presigned URLs are now createPresignedUrl
    /**
    $timeout = $args['presigned_url'] ? time() + $args['presigned_url_timeout'] : 0;
    foreach ($this->presignedUrls as $path => $timeout) {
      if ($path === '*' || preg_match('#' . strtr($path, '#', '\#') . '#', $local_path)) {
        $args['presigned_url'] = TRUE;
        $args['presigned_url_timeout'] = $timeout;
        break;
      }
    }**/

    // Save as.
    // @todo Object constructor.
    foreach ($this->config->getSaveAsPaths() as $path) {
      if ($path === '*' || preg_match('#' . strtr($path, '#', '\#') . '#', $local_path)) {
        $args['ResponseContentDisposition'] = 'attachment; filename=' . basename($local_path);
        break;
      }
    }

    // Generate a standard URL.
    $url = static::$client->getObjectUrl($this->uri->getBucket(), $this->getLocalPath());

    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public static function getMimeType($uri, $mapping = NULL) {
    // Load the default file map.
    include_once DRUPAL_ROOT . '/includes/file.mimetypes.inc';
    $mapping = file_mimetype_mapping();

    $extension = '';
    $file_parts = explode('.', basename($uri));

    // Remove the first part: a full filename should not match an extension.
    array_shift($file_parts);

    // Iterate over the file parts, trying to find a match.
    // For my.awesome.image.jpeg, we try:
    // jpeg
    // image.jpeg, and
    // awesome.image.jpeg
    while ($additional_part = array_pop($file_parts)) {
      $extension = strtolower($additional_part . ($extension ? '.' . $extension : ''));
      if (isset($mapping['extensions'][$extension])) {
        return $mapping['mimetypes'][$mapping['extensions'][$extension]];
      }
    }

    return 'application/octet-stream';
  }

  /**
   * {@inheritdoc}
   */
  public function chmod($mode) {
    // TODO: Implement chmod() method.
    return TRUE;
  }

  /**
   * @return bool
   *   FALSE, as this stream wrapper does not support realpath().
   */
  public function realpath() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function dirname($uri = NULL) {
    $s3url = S3Url::factory($uri, $this->config);
    $s3url->normalizePath();
    $pathSegments = $s3url->getPathSegments();
    array_pop($pathSegments);
    $s3url->setPath($pathSegments);
    return trim((string) $s3url, '/');
  }

  /**
   * Return the local filesystem path.
   *
   * @todo Test this.
   *
   * @return string
   *   The local path.
   */
  protected function getLocalPath() {
    if (!isset($this->uri)) {
      throw new \LogicException('A URI must be set before calling getLocalPath().');
    }

    $path = $this->uri->getPath();
    $path = trim($path, '/');
    return $path;
  }

  /**
   * Override register() to force using hook_stream_wrappers().
   *
   * @param \Aws\S3\S3Client $client
   */
  public static function register(AwsS3Client $client) {
    throw new \LogicException('Drupal handles registration of stream wrappers. Implement hook_stream_wrappers() instead.');
  }

  /**
   * Override getOptions() to default all files to be publicly readable.
   *
   * @return array
   */
  protected function getOptions() {
    $options = parent::getOptions();
    $options['ACL'] = 'public-read';
    return $options;
  }
}
