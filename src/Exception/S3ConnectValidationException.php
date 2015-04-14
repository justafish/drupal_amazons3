<?php

namespace Drupal\amazons3\Exception;

use Aws\S3\Exception\S3Exception;

/**
 * Exception thrown when credentials and bucket configurations are invalid.
 *
 * @class S3ConnectValidationException
 * @package Drupal\amazons3\Exception
 */
class S3ConnectValidationException extends S3Exception {

}
