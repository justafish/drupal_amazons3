<?php

namespace Drupal\amazons3Test\Stub;

use Drupal\amazons3Test\DrupalAdapter\Common;
use Drupal\amazons3Test\DrupalAdapter\FileMimetypes;

/**
 * Stub common and mimetype functions.
 *
 * @class StreamWrapper
 * @package Drupal\amazons3Test\Stub
 */
class StreamWrapper extends \Drupal\amazons3\StreamWrapper {
  use Common;
  use FileMimetypes;
}
