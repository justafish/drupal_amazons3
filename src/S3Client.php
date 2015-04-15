<?php

namespace Drupal\amazons3;

/**
 * @file
 * A wrapper around S3Client::factory() using aws_key / aws_secret variables.
 */

use Aws\Common\Credentials\Credentials;
use Drupal\amazons3\Exception\S3ConnectValidationException;
use Guzzle\Common\Collection;

/**
 * A wrapper around S3Client::factory() using aws_key / aws_secret variables.
 *
 * @class S3Client
 *
 * @package Drupal\amazons3
 */
class S3Client {

  /**
   * Create a new S3Client using aws_key / aws_secret $conf variables.
   *
   * @todo Needs tests.
   *
   * @param array|Collection $config
   *   An array of configuration options to pass to \Aws\S3\S3Client::factory().
   *   If 'credentials' are set they will be used instead of aws_key and
   *   aws_secret.
   *
   * @return \Aws\S3\S3Client
   */
  public static function factory($config = array()) {
    if (!isset($config['credentials'])) {
      $config['credentials'] = new Credentials(variable_get('amazons3_key'), variable_get('amazons3_secret'));
    }

    return \Aws\S3\S3Client::factory($config);
  }

  /**
   * Validate that a bucket exists.
   *
   * Since bucket names are global across all of S3, we can't determine if a
   * bucket doesn't exist at all, or if it exists but is owned by another S3
   * account.
   *
   * @todo Needs tests.
   *
   * @param string $bucket
   *   The name of the bucket to test.
   * @param \Aws\S3\S3Client $client
   *   The S3Client to use.
   *
   * @throws S3ConnectValidationException
   *   Thrown when credentials are invalid or the bucket does not exist.
   */
  public static function validateBucketExists($bucket, \Aws\S3\S3Client $client) {
    if (!$client->doesBucketExist($bucket, FALSE)) {
      throw new S3ConnectValidationException('The S3 access credentials are invalid or the bucket does not exist.');
    }
  }
}
