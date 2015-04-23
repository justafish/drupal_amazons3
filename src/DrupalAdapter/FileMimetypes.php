<?php

namespace Drupal\amazons3\DrupalAdapter;

/**
 * Methods that map to includes/file.mimetypes.inc.
 *
 * @trait FileMimetypes
 * @package Drupal\amazons3\DrupalAdapter
 * @codeCoverageIgnore
 */
trait FileMimetypes {

  /**
   * @return array
   */
  public static function file_mimetype_mapping() {
    /** @noinspection PhpIncludeInspection */
    include_once DRUPAL_ROOT . '/includes/file.mimetypes.inc';
    return file_mimetype_mapping();
  }
}
