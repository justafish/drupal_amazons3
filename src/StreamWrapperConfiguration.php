<?php

namespace Drupal\amazons3;

use Drupal\amazons3\DrupalAdapter\Bootstrap;
use Drupal\amazons3\Matchable\BasicPath;
use Drupal\amazons3\Matchable\MatchablePaths;
use Drupal\amazons3\Matchable\PresignedPath;
use Guzzle\Common\Collection;

/**
 * Class to manage S3 stream wrapper configuration.
 *
 * PHP doesn't pass in any parameters when constructing a new stream wrapper.
 * One possibility would be to use stream_context_create(), but Drupal doesn't
 * use it when registering streams. This makes it near impossible to inject
 * configuration, forcing us to rely on variable_get() and a bootstrapped
 * database.
 *
 * This class defaults to using variable_get() and so on, but can be constructed
 * manually to disable this behaviour. For this setup, use the various set
 * methods to configure the stream wrapper.
 *
 * @class StreamWrapperConfiguration
 * @package Drupal\amazons3
 */
class StreamWrapperConfiguration extends Collection {
  use Bootstrap;

  /**
   * Generate a configuration object from an array.
   *
   * @param array $config
   *   (optional) An array of configuration data. Each key is a lower-cased
   *   string corresponding with a set method.
   * @param array $defaults
   *   (optional) Override the default settings.
   * @param array $required
   *   (optional) Override the required settings.
   *
   * @return StreamWrapperConfiguration
   *   The stream wrapper configuration.
   */
  public static function fromConfig(array $config = array(), array $defaults = array(), array $required = array()) {
    if (empty($defaults)) {
      $defaults = self::defaults();
    }

    if (empty($required)) {
      $required = self::required();
    }

    $data = $config + $defaults;
    if ($data['caching']) {
      $required[] = 'expiration';
    }

    if ($missing = array_diff($required, array_keys(array_filter($data, function($item) {
      return !is_null($item) && $item !== '';
    })))) {
      throw new \InvalidArgumentException('Config is missing the following keys: ' . implode(', ', $missing));
    }

    if (!$data['domain']) {
      $data['domain'] = self::getS3Domain($data['bucket']);
    }

    return new static($data);
  }

  /**
   * @return array
   */
  protected static function defaults() {
    $defaults = array(
      'hostname' => NULL,
      'bucket' => NULL,
      'torrentPaths' => new MatchablePaths(),
      'presignedPaths' => new MatchablePaths(),
      'saveAsPaths' => new MatchablePaths(),
      'cloudFront' => FALSE,
      'cloudFrontPrivateKey' => NULL,
      'cloudFrontKeyPairId' => NULL,
      'domain' => NULL,
      'caching' => FALSE,
      'cacheLifetime' => NULL,
      'reducedRedundancyPaths' => new MatchablePaths(),
    );
    return $defaults;
  }

  /**
   * {@inheritdoc}
   */
  protected static function required() {
    $required = array(
      'bucket',
    );
    return $required;
  }

  /**
   * Get the S3 domain for a bucket.
   *
   * @param string $bucket
   *   The bucket to get the domain for.
   * @return string
   *   The S3 domain, such as bucket.s3.amazonaws.com.
   */
  protected static function getS3Domain($bucket) {
    $domain = StreamWrapper::S3_API_DOMAIN;
    // If the bucket does not contain dots, the S3 SDK generates URLs that
    // use the bucket name in the host.
    if (strpos($bucket, '.') === FALSE) {
      $domain = $bucket . '.' . $domain;
    }

    return $domain;
  }


  /**
   * Get the API hostname.
   *
   * @return string
   */
  public function getHostname() {
    return $this->data['hostname'];
  }

  /**
   * Set the API hostname.
   *
   * @param string $hostname
   */
  public function setHostname($hostname) {
    $this->data['hostname'] = $hostname;
  }

  /**
   * Get the bucket.
   *
   * @return string
   */
  public function getBucket() {
    return $this->data['bucket'];
  }

  /**
   * Set the bucket.
   *
   * @param string $bucket
   */
  public function setBucket($bucket) {
    $this->data['bucket'] = $bucket;
  }

  /**
   * Get the torrent paths.
   *
   * @return MatchablePaths
   */
  public function getTorrentPaths() {
    return $this->data['torrentPaths'];
  }

  /**
   * Set the array of paths to serve as torrents.
   *
   * @param MatchablePaths $torrentPaths
   */
  public function setTorrentPaths(MatchablePaths $torrentPaths) {
    $this->data['torrentPaths'] = $torrentPaths;
  }

  /**
   * Get the array of paths to serve with presigned URLs.
   *
   * @return MatchablePaths
   */
  public function getPresignedPaths() {
    return $this->data['presignedPaths'];
  }

  /**
   * Set the array of paths to serve with presigned URLs.
   *
   * @param MatchablePaths $presignedPaths
   */
  public function setPresignedPaths(MatchablePaths $presignedPaths) {
    $this->data['presignedPaths'] = $presignedPaths;
  }

  /**
   * Return the paths to force to download instead of viewing in the browser.
   *
   * @return MatchablePaths
   */
  public function getSaveAsPaths() {
    return $this->data['saveAsPaths'];
  }

  /**
   * Set the array of paths to force to download.
   *
   * @param MatchablePaths $saveAsPaths
   */
  public function setSaveAsPaths(MatchablePaths $saveAsPaths) {
    $this->data['saveAsPaths'] = $saveAsPaths;
  }

  /**
   * Return if files should be served with CloudFront.
   *
   * @return bool
   */
  public function isCloudFront() {
    return $this->data['cloudFront'];
  }

  /**
   * Set if objects should be served with CloudFront.
   */
  public function serveWithCloudFront() {
    $this->data['cloudFront'] = TRUE;
  }

  /**
   * Set the CloudFront credentials to use.
   *
   * @param string $path
   *   The path to the file containing the private key.
   * @param string $keyPairId
   *   The key pair ID.
   */
  public function setCloudFrontCredentials($path, $keyPairId) {
    if (!file_exists($path)) {
      throw new \InvalidArgumentException("$path does not exist.");
    }

    $this->data['cloudFrontPrivateKey'] = $path;
    $this->data['cloudFrontKeyPairId'] = $keyPairId;
  }

  /**
   * @return \Aws\CloudFront\CloudFrontClient
   */
  public function getCloudFront() {
    if (!$this->isCloudFront()) {
      throw new \LogicException('CloudFront is not enabled.');
    }

    return CloudFrontClient::factory(array(
      'private_key' => $this->data['cloudFrontPrivateKey'],
      'key_pair_id' => $this->data['cloudFrontKeyPairId'],
    ));
  }

  /**
   * Set if objects should be served with S3 directly.
   */
  public function serveWithS3() {
    $this->data['cloudFront'] = FALSE;
  }

  /**
   * @return string
   */
  public function getDomain() {
    return $this->data['domain'];
  }

  /**
   * @param string $domain
   */
  public function setDomain($domain) {
    $this->data['domain'] = $domain;
  }

  /**
   * @return boolean
   */
  public function isCaching() {
    return (bool) $this->data['caching'];
  }

  /**
   * Enable metadata caching.
   */
  public function enableCaching() {
    $this->data['caching'] = TRUE;
  }

  /**
   * Disable metadata caching.
   */
  public function disableCaching() {
    $this->data['caching'] = FALSE;
    $this->data['expiration'] = NULL;
  }

  /**
   * Set the cache expiration.
   *
   * This method must only be called if caching is enabled. Use CACHE_PERMANENT
   * to cache with no expiration.
   *
   * @param int $expiration
   */
  public function setCacheLifetime($expiration) {
    if (!$this->isCaching()) {
      throw new \LogicException('Caching must be enabled before setting a expiration.');
    }

    $this->data['expiration'] = $expiration;
  }

  /**
   * @return int
   *   The cache expiration, in seconds. Zero means expiration is disabled.
   */
  public function getCacheLifetime() {
    return $this->data['expiration'];
  }

  /**
   * @return MatchablePaths
   */
  public function getReducedRedundancyPaths() {
    return $this->data['reducedRedundancyPaths'];
  }

  /**
   * @param MatchablePaths $reducedRedundancyPaths
   */
  public function setReducedRedundancyPaths(MatchablePaths $reducedRedundancyPaths) {
    $this->data['reducedRedundancyPaths'] = $reducedRedundancyPaths;
  }

  /**
   * Set the stream wrapper configuration using Drupal variables.
   *
   * @return StreamWrapperConfiguration
   *   A StreamWrapperConfiguration object.
   */
  public static function fromDrupalVariables() {
    $config = self::fromConfig(array('bucket' => static::variable_get('amazons3_bucket', NULL)));
    $defaults = $config->defaults();

    $config->setHostname(static::variable_get('amazons3_hostname', $defaults['hostname']));

    // CNAME support for customizing S3 URLs.
    if (static::variable_get('amazons3_cname', FALSE)) {
      $domain = static::variable_get('amazons3_domain', $defaults['domain']);
      if (strlen($domain) > 0) {
        $config->setDomain($domain);
      }
      else {
        $config->setDomain($config->getBucket());
      }
      if (static::variable_get('amazons3_cloudfront', $defaults['cloudFront'])) {
        $path = static::variable_get('amazons3_cloudfront_private_key', $defaults['cloudFrontPrivateKey']);
        $keyPairId = static::variable_get('amazons3_cloudfront_keypair_id', $defaults['cloudFrontKeyPairId']);
        $config->setCloudFrontCredentials($path, $keyPairId);
        $config->serveWithCloudFront();
      }
    }
    else {
      $config->setDomain(static::getS3Domain($config->getBucket()));
    }

    // Check whether local file caching is turned on.
    if (static::variable_get('amazons3_cache', $defaults['caching'])) {
      $config->enableCaching();
      $config->setCacheLifetime(static::variable_get('amazons3_cache_expiration', NULL));
    }
    else {
      $config->disableCaching();
    }

    // Torrent list.
    $torrentPaths = static::variable_get('amazons3_torrents', array());
    $paths = BasicPath::factory($torrentPaths);
    if (!empty($paths)) {
      $config->setTorrentPaths(new MatchablePaths($paths));
    }

    // Presigned url-list.
    $presigned_urls = static::variable_get('amazons3_presigned_urls', array());
    $paths = array();
    foreach ($presigned_urls as $presigned_url) {
      $paths[] = new PresignedPath($presigned_url['pattern'], $presigned_url['timeout']);
    }
    if (!empty($paths)) {
      $config->setPresignedPaths(new MatchablePaths($paths));
    }

    // Force "save as" list.
    $saveAsPaths = static::variable_get('amazons3_saveas', array());
    $paths = BasicPath::factory($saveAsPaths);
    if (!empty($paths)) {
      $config->setSaveAsPaths(new MatchablePaths($paths));
    }

    // Reduced Redundancy Storage.
    $rrsPaths = static::variable_get('amazons3_rrs', array());
    $paths = BasicPath::factory($rrsPaths);
    if (!empty($paths)) {
      $config->setReducedRedundancyPaths(new MatchablePaths($paths));
    }

    return $config;
  }
}
