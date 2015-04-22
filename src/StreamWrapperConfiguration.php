<?php

namespace Drupal\amazons3;

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

  /**
   * Construct a new configuration for an S3 stream wrapper.
   *
   * @param bool $useVariables
   *   (optional) Use variables from variable_get() to configure S3. Defaults to
   *   TRUE. Items set in $data will override Drupal variables.
   */

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

    if ($missing = array_diff($required, array_keys($data))) {
      throw new \InvalidArgumentException('Config is missing the following keys: ' . implode(', ', $missing));
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
      'torrentPaths' => array(),
      'presignedPaths' => array(),
      'saveAsPaths' => array(),
      'cloudFront' => array(),
      'domain' => NULL,
      'caching' => TRUE,
      'cacheLifetime' => NULL,
      'reducedRedundancyPaths' => array(),
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
   * @return string[]
   */
  public function getTorrentPaths() {
    return $this->data['torrentPaths'];
  }

  /**
   * Set the array of paths to serve as torrents.
   *
   * @param string[] $torrentPaths
   */
  public function setTorrentPaths(array $torrentPaths) {
    $this->data['torrentPaths'] = $torrentPaths;
  }

  /**
   * Get the array of paths to serve with presigned URLs.
   *
   * @return string[]
   */
  public function getPresignedPaths() {
    return $this->data['presignedPaths'];
  }

  /**
   * Set the array of paths to serve with presigned URLs.
   *
   * @param string[] $presignedPaths
   */
  public function setPresignedPaths(array $presignedPaths) {
    $this->data['presignedPaths'] = $presignedPaths;
  }

  /**
   * Return the paths to force to download instead of viewing in the browser.
   *
   * @return string[]
   */
  public function getSaveAsPaths() {
    return $this->data['saveAsPaths'];
  }

  /**
   * Set the array of paths to force to download.
   *
   * @param string[] $saveAsPaths
   */
  public function setSaveAsPaths($saveAsPaths) {
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
   * @return string[]
   */
  public function getReducedRedundancyPaths() {
    return $this->data['reducedRedundancyPaths'];
  }

  /**
   * @param string[] $reducedRedundancyPaths
   */
  public function setReducedRedundancyPaths(array $reducedRedundancyPaths) {
    $this->data['reducedRedundancyPaths'] = $reducedRedundancyPaths;
  }

  /**
   * Set the stream wrapper configuration using Drupal variables.
   *
   * @return StreamWrapperConfiguration
   */
  public static function fromDrupalVariables() {
    $config = new static();
    $config = self::fromConfig(array('bucket' => variable_get('amazons3_bucket', NULL)));
    $defaults = $config->defaults();

    $config->setHostname(variable_get('amazons3_hostname', $defaults['hostname']));

    // CNAME support for customizing S3 URLs.
    if (variable_get('amazons3_cname', FALSE)) {
      $domain = variable_get('amazons3_domain', $defaults['domain']);
      if (strlen($domain) > 0) {
        $config->setDomain($domain);
      }
      else {
        $config->setDomain($config->getBucket());
      }
      if (!variable_get('amazons3_cloudfront', $defaults['cloudFront'])) {
        $config->serveWithS3();
      }
    }
    else {
      $config->setDomain($config->getBucket() . '.s3.amazonaws.com');
    }

    // Check whether local file caching is turned on.
    if (variable_get('amazons3_cache', $defaults['caching'])) {
      $config->enableCaching();
      $config->setCacheLifetime(variable_get('amazons3_cache_expiration', NULL));
    }
    else {
      $config->disableCaching();
    }

    // Torrent list.
    $torrents = explode("\n", variable_get('amazons3_torrents', $defaults['torrentPaths']));
    $torrents = array_map('trim', $torrents);
    $torrents = array_filter($torrents, 'strlen');
    $config->setTorrentPaths($torrents);

    // Presigned url-list.
    // @todo This is going to be totally broken.
    $presigned_urls = explode(
      "\n",
      variable_get('amazons3_presigned_urls', $defaults['presignedPaths'])
    );
    $presigned_urls = array_map('trim', $presigned_urls);
    $presigned_urls = array_filter($presigned_urls, 'strlen');
    $config->presignedUrls = array();
    foreach ($presigned_urls as $presigned_url) {
      // Check for an explicit key.
      $matches = array();
      if (preg_match('/(.*)\|(.*)/', $presigned_url, $matches)) {
        $config->presignedUrls[$matches[2]] = $matches[1];
      }
      else {
        $config->presignedUrls[$presigned_url] = 60;
      }
    }

    // Force "save as" list.
    $saveas = explode("\n", variable_get('amazons3_saveas', $defaults['saveAsPaths']));
    $saveas = array_map('trim', $saveas);
    $saveas = array_filter($saveas, 'strlen');
    $config->setSaveAsPaths($saveas);

    // Reduced Redundancy Storage.
    $rrs = explode("\n", variable_get('amazons3_rrs', $defaults['reducedRedundancyPaths']));
    $rrs = array_map('trim', $rrs);
    $rrs = array_filter($rrs, 'strlen');
    $config->setReducedRedundancyPaths($rrs);

    return $config;
  }
}
