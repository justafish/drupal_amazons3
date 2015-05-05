<?php

namespace Drupal\amazons3Test\Stub;

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

  protected static $called = FALSE;

  public static function resetCalled() {
    static::$called = FALSE;
  }

  /**
   * @return boolean
   */
  public static function isCalled() {
    return static::$called;
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
  public static function factory($config = array()) {
    static::$called = TRUE;
    return parent::factory($config);
  }
}
