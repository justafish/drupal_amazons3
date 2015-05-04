# AmazonS3

[![Build Status](https://travis-ci.org/justafish/drupal_amazons3.svg?branch=7.x-2.x)](https://travis-ci.org/justafish/drupal_amazons3) [![Coverage Status](https://coveralls.io/repos/justafish/drupal_amazons3/badge.svg?branch=7.x-2.x)](https://coveralls.io/r/justafish/drupal_amazons3?branch=7.x-2.x)

The AmazonS3 module allows the local file system to be replaced with S3. Uploads are saved into the Drupal file table using D7's file/stream wrapper system.

You can also use it with other [S3 compatible cloud storage services](http://en.wikipedia.org/wiki/Amazon_S3#S3_API_and_competing_services) such as [Google Cloud Storage](https://cloud.google.com/storage).

You can switch it on as the default file system scheme, or individually for file and image fields.

## Requirements
- [Composer Manager](https://www.drupal.org/project/composer_manager)
- [PHP's cURL extension](https://php.net/manual/en/book.curl.php) (nearly always available by default)

# Configuration
Most module configuration is handled at `admin/config/media/amazons3`. At a
minimum, S3 credentials and a default bucket will need to be configured. It's
best to configure these settings in `$conf` variables in `settings.php`.

To use signed CloudFront URLs, the CloudFront private key and ID are needed.
The private key is a `.pem` file, and should be stored outside of your document
root. Set `$conf['amazons3_cloudfront_private_key']` to the path of the private
key and `$conf['amazons3_cloudfront_keypair_id']` to the key ID in settings.php
to enable this feature.

## Installation
- Review the patch notes below. Nearly all sites will need the very first patch
  against Drupal's image module.
- Download and install Composer Manager and AmazonS3 Drupal modules.
- Enable the AmazonS3 module. It's easiest to use drush so it will
  automatically download the AWS SDK.
- Configure the AmazonS3 credentials and other settings at
  /admin/config/media/amazon

## Patches for full functionality

While Drupal core and contrib have basic support for remote stream wrappers,
most modules have issues where they hard code URIs or specific file systems.
All of these patches except for the Imagemagick patch add simple alter hooks,
so they should be unlikely to cause problems.

### Image styles

- [hook_image_style_path_alter](https://www.drupal.org/node/1358896#comment-9297197)
  needs to be patched in Drupal's image.module.
- To use Imagemagick, it must be patched to
  [support remote stream wrappers](https://www.drupal.org/node/1695068#comment-8953159).

## Media module, file entities, and plupload

- Drupal's <code>file.inc</code> needs to be
  [patched to add an alter hook](https://www.drupal.org/node/2479523#comment-9873165).
- Media module needs to be patched
  [for file uploads to work with an S3 bucket](https://www.drupal.org/node/2479473#comment-9872845).
- File Entity needs to be patched
  [to use correct S3 URLs](https://www.drupal.org/node/2479483#comment-9872933).
- File Entity also needs to be patched
  [to work properly with the Media Internet module](https://www.drupal.org/node/2482757#comment-9889991).

## Usage

- Change individual fields to upload to S3 in the field settings
- Use AmazonS3 instead of the public file system (although there are a few issues due to core hardcoding the use of public:// in a few places e.g. aggregated CSS and JS). Go to /admin/config/media/file-system and set the default download method to Amazon.
- When using Features to export field definitions, the Upload destination is included. If you want to override this (for example, in a multi-environment workflow), use the 'amazons3_file_uri_scheme_override' variable. See amazons3_field_default_field_bases_alter() for documentation.

## CORS Upload
See [http://drupal.org/project/amazons3_cors](http://drupal.org/project/amazons3_cors)


## API
You can modify the generated URL and it's properties, this is very useful for setting Cache-Control and Expires headers (as long as you aren't using CloudFront).

You can also alter the metadata for each object saved to S3 with hook_amazons3_save_headers(). This is very useful for forcing the content-disposition header to force download files if they're being delivered through CloudFront presigned URLs.

See amazons3.api.php

## Running PHPUnit tests

The included unit tests do not have any dependency on a Drupal installation. By
using PHPUnit, you can integrate test results into your IDE of choice.

* Run `composer install` in the module directory.
* Run `vendor/bin/phpunit tests`.

In PHPStorm, it's easiest to configure PHPUnit to use the autoloader generated
in vendor/autoload.php. It's also good to mark the vendor directory as
excluded, if you already have a vendor directory indexed from composer_manager.
