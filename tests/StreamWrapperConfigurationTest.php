<?php

namespace Drupal\amazons3Test;

use Drupal\amazons3\Matchable\MatchablePaths;
use Drupal\amazons3Test\Stub\StreamWrapperConfiguration;

/**
 * @class StreamWrapperConfigurationTest
 * @package Drupal\amazons3Test
 */
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

  /**
   * @covers Drupal\amazons3\StreamWrapperConfiguration::fromConfig
   * @expectedException \InvalidArgumentException
   */
  public function testMissingBucket() {
    StreamWrapperConfiguration::fromConfig();
  }

  /**
   * @covers Drupal\amazons3\StreamWrapperConfiguration
   */
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

    $config->setDomain('cdn.example.com');
    $this->assertEquals('cdn.example.com', $config->getDomain());

    $config->setHostname('api.example.com');
    $this->assertEquals('api.example.com', $config->getHostname());

    $config->serveWithCloudFront();
    $this->assertTrue($config->isCloudFront());

    $config->serveWithS3();
    $this->assertFalse($config->isCloudFront());

    $mp = new MatchablePaths(array('/'));
    $config->setPresignedPaths($mp);
    $this->assertEquals($mp, $config->getPresignedPaths());

    $config->setReducedRedundancyPaths($mp);
    $this->assertEquals($mp, $config->getReducedRedundancyPaths());

    $config->setSaveAsPaths($mp);
    $this->assertEquals($mp, $config->getSaveAsPaths());

    $config->setTorrentPaths($mp);
    $this->assertEquals($mp, $config->getTorrentPaths());

    $config->serveWithCloudFront();
    $config->setCloudFrontCredentials('/dev/null', 'keypair-id');
    $this->assertInstanceOf('Aws\CloudFront\CloudFrontClient', $config->getCloudFront());
  }

  /**
   * @expectedException \LogicException
   */
  public function testCacheLifetimeException() {
    $config = StreamWrapperConfiguration::fromConfig(array('bucket' => 'bucket'));
    $config->setCacheLifetime(0);
  }

  /**
   * @covers Drupal\amazons3\StreamWrapperConfiguration
   * @expectedException \InvalidArgumentException
   */
  public function testCloudFrontCredentials() {
    $config = StreamWrapperConfiguration::fromConfig(array('bucket' => 'bucket'));
    $config->setCloudFrontCredentials('/does-not-exist', 'asdf');
  }

  /**
   * @covers Drupal\amazons3\StreamWrapperConfiguration
   * @expectedException \LogicException
   */
  public function testCloudFrontNotSetUp() {
    $config = StreamWrapperConfiguration::fromConfig(array('bucket' => 'bucket'));
    $config->getCloudFront();
  }

  /**
   * @covers Drupal\amazons3\StreamWrapperConfiguration::fromConfig
   */
  public function testDefaultHostname() {
    $config = StreamWrapperConfiguration::fromConfig(array('bucket' => 'bucket'));
    $this->assertEquals('bucket.s3.amazonaws.com', $config->getDomain());
  }

  /**
   * @covers Drupal\amazons3\StreamWrapperConfiguration::fromDrupalVariables
   * @covers Drupal\amazons3\StreamWrapperConfiguration::getS3Domain
   */
  public function testFromDrupalVariables() {
    StreamWrapperConfiguration::setVariableData([
      'amazons3_bucket' => 'default.example.com',
      'amazons3_hostname' => 'api.example.com',
      'amazons3_cname' => TRUE,
      'amazons3_domain' => 'static.example.com',
      'amazons3_cloudfront' => TRUE,
      'amazons3_cloudfront_private_key' => '/dev/null',
      'amazons3_cloudfront_keypair_id' => 'example',
      'amazons3_cache' => TRUE,
      'amazons3_torrents' => array('.*'),
      'amazons3_presigned_urls' => array(array('pattern' => '.*', 'timeout' => '60')),
      'amazons3_saveas' => array('.*'),
      'amazons3_rrs' => array('.*'),
    ]);

    $config = StreamWrapperConfiguration::fromDrupalVariables();
    $this->assertEquals($config->getBucket(), 'default.example.com');
    $this->assertEquals($config->getHostname(), 'api.example.com');
    $this->assertEquals($config->getDomain(), 'static.example.com');
    $this->assertEquals($config->isCloudFront(), TRUE);
    $this->assertInstanceOf('Aws\CloudFront\CloudFrontClient', $config->getCloudFront());
    $this->assertEquals($config->isCaching(), TRUE);
    $this->assertInstanceOf('Drupal\amazons3\Matchable\MatchablePaths', $config->getTorrentPaths());
    $this->assertInstanceOf('Drupal\amazons3\Matchable\MatchablePaths', $config->getPresignedPaths());
    $this->assertInstanceOf('Drupal\amazons3\Matchable\MatchablePaths', $config->getSaveAsPaths());
    $this->assertInstanceOf('Drupal\amazons3\Matchable\MatchablePaths', $config->getReducedRedundancyPaths());

    StreamWrapperConfiguration::setVariableData([
      'amazons3_bucket' => 'default.example.com',
      'amazons3_cname' => TRUE,
      'amazons3_cache' => FALSE,
    ]);
    $config = StreamWrapperConfiguration::fromDrupalVariables();
    $this->assertEquals($config->getBucket(), $config->getDomain());
    $this->assertFalse($config->isCaching());

    // When the bucket has a dot, check that the bucket is not in the domain.
    StreamWrapperConfiguration::setVariableData([
      'amazons3_bucket' => 'default.example.com',
    ]);
    $config = StreamWrapperConfiguration::fromDrupalVariables();
    $this->assertEquals('s3.amazonaws.com', $config->getDomain());

    // When the bucket does not have a dot, check the bucket is in the
    // subdomain.
    StreamWrapperConfiguration::setVariableData([
      'amazons3_bucket' => 'bucket',
    ]);
    $config = StreamWrapperConfiguration::fromDrupalVariables();
    $this->assertEquals('bucket.s3.amazonaws.com', $config->getDomain());

    StreamWrapperConfiguration::setVariableData(array());
  }

  /**
   * @covers Drupal\amazons3\StreamWrapperConfiguration::fromConfig
   * @covers Drupal\amazons3\StreamWrapperConfiguration::getS3Domain
   * @expectedException \InvalidArgumentException
   */
  public function testEmptyRequiredStringFails() {
    StreamWrapperConfiguration::fromConfig(['bucket' => '']);
  }
}
