<?php

namespace Drupal\amazons3;

use Drupal\amazons3Test\Stub\S3Client as DrupalS3Client;
use Drupal\amazons3Test\Stub\StreamWrapperConfiguration;
use Guzzle\Http\Message\Response;
use Guzzle\Tests\GuzzleTestCase;

class S3ClientTest extends GuzzleTestCase {

  /**
   * @covers Drupal\amazons3\S3Client::factory
   */
  public function testFactory() {
    DrupalS3Client::setVariableData([
      'amazons3_key' => 'placeholder',
      'amazons3_secret' => 'placeholder',
    ]);
    $client = DrupalS3Client::factory();
    $this->assertInstanceOf('Aws\S3\S3Client', $client);
    $this->assertEquals('placeholder', $client->getCredentials()->getAccessKeyId('placeholder'));

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
    // Instantiate the AWS service builder.
    $config = array (
      'includes' =>
        array (
          0 => '_aws',
        ),
      'services' =>
        array (
          'default_settings' =>
            array (
              'params' =>
                array (
                  'region' => 'us-east-1',
                ),
            ),
          'cloudfront' =>
            array (
              'extends' => 'cloudfront',
              'params' =>
                array (
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
    $client = $this->getServiceBuilder()->get('s3', true);

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
}
