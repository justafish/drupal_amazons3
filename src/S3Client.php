<?php

namespace Drupal\amazons3;

/**
 * @file
 * A wrapper around S3Client::factory() using aws_key / aws_secret variables.
 */

use Aws\Common\Credentials\Credentials;
use Drupal\amazons3\Exception\S3ConnectValidationException;
use Guzzle\Common\Collection;
use Guzzle\Service\Command\Factory\AliasFactory;

/**
 * A wrapper around S3Client::factory() using aws_key / aws_secret variables.
 *
 * @class S3Client
 *
 * @package Drupal\amazons3
 */
class S3Client {
  use DrupalAdapter\Bootstrap;

  /**
   * This set of commandAliases is a protected static with no getter on the
   * S3Client class.
   *
   * @var array
   */
  protected static $commandAliases = array(
    // REST API Docs Aliases
    'GetService' => 'ListBuckets',
    'GetBucket'  => 'ListObjects',
    'PutBucket'  => 'CreateBucket',

    // SDK 1.x Aliases
    'GetBucketHeaders'              => 'HeadBucket',
    'GetObjectHeaders'              => 'HeadObject',
    'SetBucketAcl'                  => 'PutBucketAcl',
    'CreateObject'                  => 'PutObject',
    'DeleteObjects'                 => 'DeleteMultipleObjects',
    'PutObjectCopy'                 => 'CopyObject',
    'SetObjectAcl'                  => 'PutObjectAcl',
    'GetLogs'                       => 'GetBucketLogging',
    'GetVersioningStatus'           => 'GetBucketVersioning',
    'SetBucketPolicy'               => 'PutBucketPolicy',
    'CreateBucketNotification'      => 'PutBucketNotification',
    'GetBucketNotifications'        => 'GetBucketNotification',
    'CopyPart'                      => 'UploadPartCopy',
    'CreateWebsiteConfig'           => 'PutBucketWebsite',
    'GetWebsiteConfig'              => 'GetBucketWebsite',
    'DeleteWebsiteConfig'           => 'DeleteBucketWebsite',
    'CreateObjectExpirationConfig'  => 'PutBucketLifecycle',
    'GetObjectExpirationConfig'     => 'GetBucketLifecycle',
    'DeleteObjectExpirationConfig'  => 'DeleteBucketLifecycle',
  );

  /**
   * Create a new S3Client using aws_key / aws_secret $conf variables.
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
      $config['credentials'] = new Credentials(static::variable_get('amazons3_key'), static::variable_get('amazons3_secret'));
    }

    $curl_defaults = array(
      CURLOPT_CONNECTTIMEOUT => 30,
    );

    if (!isset($config['curl.options'])) {
      $config['curl.options'] = array();
    }

    $config['curl.options'] += $curl_defaults;

    $client = \Aws\S3\S3Client::factory($config);
    static::setCommandFactory($client);

    return $client;
  }

  /**
   * Validate that a bucket exists.
   *
   * Since bucket names are global across all of S3, we can't determine if a
   * bucket doesn't exist at all, or if it exists but is owned by another S3
   * account.
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

  /**
   * Override the command factory on a client.
   *
   * @param \Aws\S3\S3Client $client
   *   The client to override.
   *
   * @codeCoverageIgnore
   */
  protected static function setCommandFactory($client) {
    $default = CompositeFactory::getDefaultChain($client);
    $default->add(
      new AliasFactory($client, static::$commandAliases),
      'Guzzle\Service\Command\Factory\ServiceDescriptionFactory'
    );
    $client->setCommandFactory($default);
  }
}
