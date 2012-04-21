<?php

/**
 * @file
 * This file contains no working PHP code; it exists to provide additional
 * documentation for doxygen as well as to document hooks in the standard
 * Drupal manner.
 */

/**
 * @defgroup amazons3_hooks AmazonS3 hooks
 * Hooks that can be implemented by other modules in order to extend AmazonS3.
 */

/**
 * Allows other modules to change the format and options used when
 * creating an external URL. For example the URL can be a URL directly to the
 * file, or can be a URL to a torrent. In addition, it can be authenticated
 * (time limited), and in that case a save-as can be forced.
 * @param $local_path
 *   The local filesystem path.
 * @param $info
 *   Array of keyed elements:
 *     - 'download_type': either 'http' or 'torrent'.
 *     - 'torrent': (boolean) Causes use of an authenticated URL (time limited)
 *     - 'presigned_url_timeout': (boolean) Time in seconds before an authenticated URL will time out.
 *     - 'response': array of additional options as described at
 *       http://docs.amazonwebservices.com/AWSSDKforPHP/latest/index.html#m=AmazonS3/get_object_url
 * @return
 *   The modified array of configuration items.
 */
function hook_amazons3_url_info($local_path, $info) {
  if ($local_path == 'myfile.jpg') {
    $info['presigned_url'] = TRUE;
    $info['presigned_url_timeout'] = 10;
  }
  return $info;
}

