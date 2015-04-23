<?php

namespace Drupal\amazons3Test;

use Drupal\amazons3\StreamWrapperConfiguration;

class StreamWrapperConfigurationTest extends \PHPUnit_Framework_TestCase {

  /**
   * @covers Drupal\amazons3\StreamWrapperConfiguration::fromConfig
   * @covers Drupal\amazons3\StreamWrapperConfiguration::defaults
   * @covers Drupal\amazons3\StreamWrapperConfiguration::required
   */
  public function testFromConfig() {
    $settings = array('bucket' => 'bucket');
    $config = StreamWrapperConfiguration::fromConfig($settings);
    $this->assertInstanceOf('Drupal\amazons3\StreamWrapperConfiguration', $config);
  }

  /**
   * @covers Drupal\amazons3\StreamWrapperConfiguration::fromConfig
   * @expectedException \InvalidArgumentException
   */
  public function testFromConfigMissingExpiration() {
    $settings = array('bucket' => 'bucket', 'caching' => TRUE);
    $config = StreamWrapperConfiguration::fromConfig($settings);
  }

  public function testSetters() {
    $config = StreamWrapperConfiguration::fromConfig(array('bucket' => 'bucket'));

    $config->setBucket('different-bucket');
    $this->assertEquals('different-bucket', $config->getBucket());

    $config->enableCaching();
    $this->assertTrue($config->isCaching());

    $config->setCacheLifetime(666);
    $this->assertEquals(666, $config->getCacheLifetime());

    $config->disableCaching();
    $this->assertFalse($config->isCaching());

    $config->setDomain('api.example.com');
    $this->assertEquals('api.example.com', $config->getDomain());

    $config->setHostname('cdn.example.com');
    $this->assertEquals('cdn.example.com', $config->getHostname());

    $config->serveWithCloudFront();
    $this->assertTrue($config->isCloudFront());

    $config->serveWithS3();
    $this->assertFalse($config->isCloudFront());

    $config->setPresignedPaths(array('/'));
    $this->assertEquals(array('/'), $config->getPresignedPaths());

    $config->setReducedRedundancyPaths(array('/'));
    $this->assertEquals(array('/'), $config->getReducedRedundancyPaths());

    $config->setSaveAsPaths(array('/'));
    $this->assertEquals(array('/'), $config->getSaveAsPaths());

    $config->setTorrentPaths(array('/'));
    $this->assertEquals(array('/'), $config->getTorrentPaths());
  }

  /**
   * @expectedException \LogicException
   */
  public function testCacheLifetimeException() {
    $config = StreamWrapperConfiguration::fromConfig(array('bucket' => 'bucket'));
    $config->setCacheLifetime(0);
  }
}
