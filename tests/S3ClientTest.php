<?php

namespace Drupal\amazons3;

use Drupal\amazons3Test\Stub\S3Client as DrupalS3Client;
use Guzzle\Http\Message\Response;
use Guzzle\Tests\GuzzleTestCase;

/**
 * Tests \Drupal\amazons3\S3Client.
 *
 * @class S3ClientTest
 * @package Drupal\amazons3
 */
class S3ClientTest extends GuzzleTestCase {

  /**
   * @covers Drupal\amazons3\S3Client::factory
   */
  public function testFactory() {
    DrupalS3Client::setVariableData([
      'amazons3_key' => 'key',
      'amazons3_secret' => 'secret',
      'amazons3_hostname' => 'hostname',
      'amazons3_region' => 'region',
    ]);
    DrupalS3Client::resetCalled();
    $client = DrupalS3Client::factory(array(), 'fake-bucket');
    $this->assertInstanceOf('Aws\S3\S3Client', $client);
    $this->assertEquals('key', $client->getCredentials()->getAccessKeyId());
    $this->assertEquals('secret', $client->getCredentials()->getSecretKey());
    $this->assertEquals('hostname', $client->getBaseUrl());
    $this->assertEquals('region', $client->getRegion());

    DrupalS3Client::setVariableData(array());
  }

  /**
   * @covers \Drupal\amazons3\S3Client::validateBucketExists
   * @expectedException \Drupal\amazons3\Exception\S3ConnectValidationException
   */
  public function testValidateBucketExistsFail() {
    $client = DrupalS3Client::factory();
    DrupalS3Client::validateBucketExists('bucket', $client);
  }

  /**
   * @covers \Drupal\amazons3\S3Client::validateBucketExists
   */
  public function testValidateBucketExists() {
    $client = $this->mockClient();

    $this->setMockResponse($client, array(new Response(200)));

    $exception = NULL;
    try {
      DrupalS3Client::validateBucketExists('bucket', $client);
    }
    catch (\Exception $exception) {
    }
    $this->assertNull($exception, 'The bucket was validated to exist.');
  }

  /**
   * @covers \Drupal\amazons3\S3Client::factory
   */
  public function testCurlOptions() {
    $client = DrupalS3Client::factory();
    $curl = $client->getConfig('curl.options');
    $this->assertArraySubset([CURLOPT_CONNECTTIMEOUT => 30], $curl);

    $config = ['curl.options' => [ CURLOPT_CONNECTTIMEOUT => 10 ]];
    $client = DrupalS3Client::factory($config);
    $curl = $client->getConfig('curl.options');
    $this->assertArraySubset([CURLOPT_CONNECTTIMEOUT => 10], $curl);

    $config = ['curl.options' => [ CURLOPT_VERBOSE => TRUE ]];
    $client = DrupalS3Client::factory($config);
    $curl = $client->getConfig('curl.options');
    $this->assertArraySubset([CURLOPT_CONNECTTIMEOUT => 30], $curl);
    $this->assertArraySubset([CURLOPT_VERBOSE => TRUE], $curl);

  }

  /**
   * @covers \Drupal\amazons3\S3Client::getBucketLocation
   */
  public function testGetBucketLocation() {
    $client = $this->mockClient();
    $responseBody =<<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<LocationConstraint xmlns="http://s3.amazonaws.com/doc/2006-03-01/">fake-region</LocationConstraint>
EOD;

    $this->setMockResponse($client, array(new Response(200, array(), $responseBody)));
    $this->assertEquals('fake-region', \Drupal\amazons3\S3Client::getBucketLocation('example-bucket', $client));
  }

  /**
   * Generate a mock client ready to mock HTTP requests.
   *
   * @return \Aws\S3\S3Client
   */
  protected function mockClient() {
    // Instantiate the AWS service builder.
    $config = array(
      'includes' =>
        array(
          0 => '_aws',
        ),
      'services' =>
        array(
          'default_settings' =>
            array(
              'params' =>
                array(
                  'region' => 'us-east-1',
                ),
            ),
          'cloudfront' =>
            array(
              'extends' => 'cloudfront',
              'params' =>
                array(
                  'private_key' => 'change_me',
                  'key_pair_id' => 'change_me',
                ),
            ),
        ),
      'credentials' => array('key' => 'placeholder', 'secret' => 'placeholder'),
    );
    $aws = \Aws\Common\Aws::factory($config);

    // Configure the tests to use the instantiated AWS service builder
    \Guzzle\Tests\GuzzleTestCase::setServiceBuilder($aws);
    $client = $this->getServiceBuilder()->get('s3', TRUE);
    return $client;
  }
}
