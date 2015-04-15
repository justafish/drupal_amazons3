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
   * The hostname to use for API requests.
   *
   * This can be used to work with an API-compatible service like Google's
   * Cloud Storage.
   *
   * @var string
   */
  protected $hostname;

  /**
   * The bucket to use.
   *
   * @var string
   */
  protected $bucket;

  /**
   * The domain to serve content from.
   *
   * @var string
   */
  protected $domain;

  /**
   * If files should be served from CloudFront.
   *
   * @var boolean
   */
  protected $cloudFront = TRUE;

  /**
   * An array of paths to serve as torrents.
   *
   * @var string[]
   */
  protected $torrentPaths;

  /**
   * An array of paths and timeouts to serve with presigned URLs.
   *
   * @var string[]
   */
  protected $presignedPaths;

  /**
   * An array of paths to force the user to save a file.
   *
   * @var string[]
   */
  protected $saveAsPaths;

  /**
   * If file caching is enabled.
   *
   * @var boolean
   */
  protected $caching = TRUE;

  /**
   * An array of paths to save in Reduced Redundancy Storage.
   *
   * @var string[]
   */
  protected $reducedRedundancyPaths;

  /**
   * Construct a new configuration for an S3 stream wrapper.
   *
   * @param array $config
   *   (optional) An array of configuration data. Each key is a lower-cased
   *   string corresponding with a set method.
   *
   * @param bool $useVariables
   *   (optional) Use variables from variable_get() to configure S3. Defaults to
   *   TRUE. Items set in $data will override Drupal variables.
   *
   */
  public function __construct(array $config = array(), $useVariables = TRUE) {
    if ($useVariables) {
      $this->setFromDrupalVariables();
    }
  }

  /**
   * Get the API hostname.
   *
   * @return string
   */
  public function getHostname() {
    return $this->hostname;
  }

  /**
   * Set the API hostname.
   *
   * @param string $hostname
   */
  public function setHostname($hostname) {
    $this->hostname = $hostname;
  }

  /**
   * Get the bucket.
   *
   * @return string
   */
  public function getBucket() {
    return $this->bucket;
  }

  /**
   * Set the bucket.
   *
   * @param string $bucket
   */
  public function setBucket($bucket) {
    $this->bucket = $bucket;
  }

  /**
   * Get the torrent paths.
   *
   * @return string[]
   */
  public function getTorrentPaths() {
    return $this->torrentPaths;
  }

  /**
   * Set the array of paths to serve as torrents.
   *
   * @param string[] $torrentPaths
   */
  public function setTorrentPaths(array $torrentPaths) {
    $this->torrentPaths = $torrentPaths;
  }

  /**
   * Get the array of paths to serve with presigned URLs.
   *
   * @return string[]
   */
  public function getPresignedPaths() {
    return $this->presignedPaths;
  }

  /**
   * Set the array of paths to serve with presigned URLs.
   *
   * @param string[] $presignedPaths
   */
  public function setPresignedPaths(array $presignedPaths) {
    $this->presignedPaths = $presignedPaths;
  }

  /**
   * Return the paths to force to download instead of viewing in the browser.
   *
   * @return string[]
   */
  public function getSaveAsPaths() {
    return $this->saveAsPaths;
  }

  /**
   * Set the array of paths to force to download.
   *
   * @param string[] $saveAsPaths
   */
  public function setSaveAsPaths($saveAsPaths) {
    $this->saveAsPaths = $saveAsPaths;
  }

  /**
   * Return if files should be served with CloudFront.
   *
   * @return bool
   */
  public function isCloudFront() {
    return $this->cloudFront;
  }

  /**
   * Set if objects should be served with CloudFront.
   */
  public function serveWithCloudFront() {
    $this->cloudFront = TRUE;
  }

  /**
   * Set if objects should be served with S3 directly.
   */
  public function serveWithS3() {
    $this->cloudFront = FALSE;
  }

  /**
   * @return string
   */
  public function getDomain() {
    return $this->domain;
  }

  /**
   * @param string $domain
   */
  public function setDomain($domain) {
    $this->domain = $domain;
  }

  /**
   * @return boolean
   */
  public function isCaching() {
    return $this->caching;
  }

  /**
   *
   */
  public function enableCaching() {
    $this->caching = TRUE;
  }

  /**
   *
   */
  public function disableCaching() {
    $this->caching = FALSE;
  }

  /**
   * @return string[]
   */
  public function getReducedRedundancyPaths() {
    return $this->reducedRedundancyPaths;
  }

  /**
   * @param string[] $reducedRedundancyPaths
   */
  public function setReducedRedundancyPaths(array $reducedRedundancyPaths) {
    $this->reducedRedundancyPaths = $reducedRedundancyPaths;
  }

  /**
   * Set the stream wrapper configuration using Drupal variables.
   */
  protected function setFromDrupalVariables() {
    $this->setHostname(variable_get('amazons3_hostname'));
    $this->setBucket(variable_get('amazons3_bucket'));

    // CNAME support for customizing S3 URLs.
    if (variable_get('amazons3_cname', FALSE)) {
      $domain = variable_get('amazons3_domain', '');
      if (strlen($domain) > 0) {
        $this->setDomain($domain);
      }
      else {
        $this->setDomain($this->bucket);
      }
      if (!variable_get('amazons3_cloudfront', TRUE)) {
        $this->serveWithS3();
      }
    }
    else {
      $this->setDomain($this->bucket . '.s3.amazonaws.com');
    }

    // Check whether local file caching is turned on.
    if (!variable_get('amazons3_cache', TRUE)) {
      $this->disableCaching();
    }

    // Torrent list.
    $torrents = explode("\n", variable_get('amazons3_torrents', ''));
    $torrents = array_map('trim', $torrents);
    $torrents = array_filter($torrents, 'strlen');
    $this->setTorrentPaths($torrents);

    // Presigned url-list.
    // @todo This is going to be totally broken.
    $presigned_urls = explode(
      "\n",
      variable_get('amazons3_presigned_urls', '')
    );
    $presigned_urls = array_map('trim', $presigned_urls);
    $presigned_urls = array_filter($presigned_urls, 'strlen');
    $this->presignedUrls = array();
    foreach ($presigned_urls as $presigned_url) {
      // Check for an explicit key.
      $matches = array();
      if (preg_match('/(.*)\|(.*)/', $presigned_url, $matches)) {
        $this->presignedUrls[$matches[2]] = $matches[1];
      }
      else {
        $this->presignedUrls[$presigned_url] = 60;
      }
    }

    // Force "save as" list.
    $saveas = explode("\n", variable_get('amazons3_saveas', ''));
    $saveas = array_map('trim', $saveas);
    $saveas = array_filter($saveas, 'strlen');
    $this->setSaveAsPaths($saveas);

    // Reduced Redundancy Storage.
    $rrs = explode("\n", variable_get('amazons3_rrs', ''));
    $rrs = array_map('trim', $rrs);
    $rrs = array_filter($rrs, 'strlen');
    $this->setReducedRedundancyPaths($rrs);
  }
}
