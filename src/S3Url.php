<?php

namespace Drupal\amazons3;

use Guzzle\Http\QueryString;
use Guzzle\Http\Url;

/**
 * Represents an s3:// stream URL.
 *
 * @class S3Url
 * @package Drupal\amazons3
 */
class S3Url extends Url {

  /**
   * Override __construct() to default scheme to s3.
   *
   * @param string $bucket
   *   The bucket to use for the URL.
   * @param string $key
   *   (optional) Key for the URL.
   */
  public function __construct($bucket, $key = null) {
    if ($key) {
      $key = '/' . $key;
    }

    parent::__construct('s3', $bucket, null, null, null, $key);
  }


  /**
   * Return the bucket associated with the URL.
   *
   * @return string
   */
  public function getBucket() {
    return $this->getHost();
  }

  /**
   * Set the bucket.
   *
   * @param string $bucket
   */
  public function setBucket($bucket) {
    $this->setHost($bucket);
  }

  /**
   * Return the S3 object key.
   *
   * @return string
   */
  public function getKey() {
    // Remove the leading slash getPath() keeps in the path.
    return substr($this->getPath(), 1);
  }

  /**
   * Set the S3 object key.
   *
   * This automatically prepends a slash to the path.
   *
   * @param string $key
   */
  public function setKey($key) {
    $this->setPath('/' . $key);
  }

  /**
   * Set the path part of the URL.
   *
   * Since we are using these URLs in a non-HTTP context, we don't replace
   * spaces or question marks.
   *
   * @param array|string $path Path string or array of path segments
   *
   * @return Url
   */
  public function setPath($path)
  {
    if (is_array($path)) {
      $path = '/' . implode('/', $path);
    }

    $this->path = $path;

    return $this;
  }

  /**
   * Return the image style URL associated with this URL.
   *
   * @param string $styleName
   *   The name of the image style.
   *
   * @return \Drupal\amazons3\S3Url
   *   An image style URL.
   */
  public function getImageStyleUrl($styleName) {
    $styleUrl = new S3Url($this->getBucket());
    $styleUrl->setPath("/styles/$styleName/" . $this->getKey());
    return $styleUrl;
  }

  /**
   * Overrides factory() to support bucket configs.
   *
   * @param string $url
   *   Full URL used to create a Url object.
   * @param \Drupal\amazons3\StreamWrapperConfiguration $config
   *   (optional) Configuration to associate with this URL.
   *
   * @throws \InvalidArgumentException
   *   Thrown when $url cannot be parsed by parse_url().
   *
   * @return static
   *   An S3Url.
   */
  public static function factory($url, StreamWrapperConfiguration $config = null) {
    !$config ? $bucket = null : $bucket = $config->getBucket();

    $defaults = array('scheme' => 's3', 'host' => $bucket, 'path' => null, 'port' => null, 'query' => null,
      'user' => null, 'pass' => null, 'fragment' => null);

    if (false === ($parts = parse_url($url))) {
      throw new \InvalidArgumentException('Was unable to parse malformed url: ' . $url);
    }

    $parts += $defaults;

    return new static($parts['host'], substr($parts['path'], 1));
  }
}
