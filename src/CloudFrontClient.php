<?php

namespace Drupal\amazons3;

/**
 * @class CloudFrontClient
 * @package Drupal\amazons3
 */
class CloudFrontClient extends \Aws\CloudFront\CloudFrontClient {
  use DrupalAdapter\Bootstrap;

  /**
   * Override factory() to set credential defaults.
   */
  public static function factory($config = array()) {
    if (!isset($config['private_key'])) {
      $config['private_key'] = static::variable_get('amazons3_cloudfront_private_key');
      $config['key_pair_id'] = static::variable_get('amazons3_cloudfront_keypair_id');
    }

    return parent::factory($config);
  }

}
