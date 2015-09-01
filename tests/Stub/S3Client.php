<?php

namespace Drupal\amazons3Test\Stub;

use Aws\S3\S3Client as AwsS3Client;
use Drupal\amazons3Test\DrupalAdapter\Bootstrap;
use Guzzle\Service\Command\Factory\AliasFactory;

/**
 * Stub bootstrap functions.
 *
 * @class S3Client
 * @package Drupal\amazons3Test\Stub
 * @codeCoverageIgnore
 */
class S3Client extends \Drupal\amazons3\S3Client {
  use Bootstrap;

  protected static $factoryCalled = FALSE;
  protected static $setRegionCalled = FALSE;

  public static function resetCalled() {
    static::$factoryCalled = FALSE;
    static::$setRegionCalled = FALSE;
  }

  /**
   * @return boolean
   */
  public static function isFactoryCalled() {
    return static::$factoryCalled;
  }

  /**
   * @return boolean
   */
  public static function isSetRegionCalled() {
    return self::$setRegionCalled;
  }

  /**
   * Override setCommandFactory to use our stub.
   *
   * @param \Aws\S3\S3Client $client
   */
  protected static function setCommandFactory($client) {
    $default = CompositeFactory::getDefaultChain($client);
    $default->add(
      new AliasFactory($client, static::$commandAliases),
      'Guzzle\Service\Command\Factory\ServiceDescriptionFactory'
    );
    $client->setCommandFactory($default);
  }

  /**
   * {@inheritdoc}
   */
  public static function factory($config = array(), $bucket = NULL) {
    static::$factoryCalled = TRUE;
    return parent::factory($config, $bucket);
  }

  /**
   * {@inheritdoc}
   *
   * This stub method simply records if a setRegion call has been made.
   */
  public static function setRegion($bucket, AwsS3Client $client) {
    static::$setRegionCalled = TRUE;
  }
}
