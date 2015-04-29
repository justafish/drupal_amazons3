<?php

namespace Drupal\amazons3;

use Guzzle\Service\ClientInterface;
use Guzzle\Service\Command\Factory\ConcreteClassFactory;
use Guzzle\Service\Command\Factory\ServiceDescriptionFactory;

/**
 * Override CompositeFactory to inject hook calls.
 *
 * @class CompositeFactory
 * @package Drupal\amazons3
 */
class CompositeFactory extends \Guzzle\Service\Command\Factory\CompositeFactory {
  use DrupalAdapter\Module;

  /**
   * {@inheritdoc}
   */
  public function factory($name, array $args = array()) {
    $args = array_merge($args, $this->module_invoke_all('amazons3_command_prepare', $name, $args));
    $this->drupal_alter('amazons3_command_prepare', $name, $args);

    $command = parent::factory($name, $args);
    $this->drupal_alter('amazons3_command', $command);
    return $command;
  }

  /**
   * Get the default chain to use with clients
   *
   * @param ClientInterface $client Client to base the chain on
   *
   * @return self
   * @codeCoverageIgnore This is now fixed upstream to use static.
   */
  public static function getDefaultChain(ClientInterface $client)
  {
    $factories = array();
    if ($description = $client->getDescription()) {
      $factories[] = new ServiceDescriptionFactory($description);
    }
    $factories[] = new ConcreteClassFactory($client);

    return new static($factories);
  }

}
