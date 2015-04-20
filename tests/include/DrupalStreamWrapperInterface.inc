<?php
/**
 * @file
 * Copy in Drupal's stream wrapper interfaces so we don't rely on finding a
 * Drupal installation to run PHPUnit tests.
 */

/**
 * Generic PHP stream wrapper interface.
 *
 * @see http://www.php.net/manual/class.streamwrapper.php
 */
interface StreamWrapperInterface {
  public function stream_open($uri, $mode, $options, &$opened_url);
  public function stream_close();
  public function stream_lock($operation);
  public function stream_read($count);
  public function stream_write($data);
  public function stream_eof();
  public function stream_seek($offset, $whence);
  public function stream_flush();
  public function stream_tell();
  public function stream_stat();
  public function unlink($uri);
  public function rename($from_uri, $to_uri);
  public function mkdir($uri, $mode, $options);
  public function rmdir($uri, $options);
  public function url_stat($uri, $flags);
  public function dir_opendir($uri, $options);
  public function dir_readdir();
  public function dir_rewinddir();
  public function dir_closedir();
}

/**
 * Drupal stream wrapper extension.
 *
 * Extend the StreamWrapperInterface with methods expected by Drupal stream
 * wrapper classes.
 */
interface DrupalStreamWrapperInterface extends StreamWrapperInterface {
  /**
   * Set the absolute stream resource URI.
   *
   * This allows you to set the URI. Generally is only called by the factory
   * method.
   *
   * @param $uri
   *   A string containing the URI that should be used for this instance.
   */
  function setUri($uri);

  /**
   * Returns the stream resource URI.
   *
   * @return
   *   Returns the current URI of the instance.
   */
  public function getUri();

  /**
   * Returns a web accessible URL for the resource.
   *
   * This function should return a URL that can be embedded in a web page
   * and accessed from a browser. For example, the external URL of
   * "youtube://xIpLd0WQKCY" might be
   * "http://www.youtube.com/watch?v=xIpLd0WQKCY".
   *
   * @return
   *   Returns a string containing a web accessible URL for the resource.
   */
  public function getExternalUrl();

  /**
   * Returns the MIME type of the resource.
   *
   * @param $uri
   *   The URI, path, or filename.
   * @param $mapping
   *   An optional map of extensions to their mimetypes, in the form:
   *    - 'mimetypes': a list of mimetypes, keyed by an identifier,
   *    - 'extensions': the mapping itself, an associative array in which
   *      the key is the extension and the value is the mimetype identifier.
   *
   * @return
   *   Returns a string containing the MIME type of the resource.
   */
  public static function getMimeType($uri, $mapping = NULL);

  /**
   * Changes permissions of the resource.
   *
   * PHP lacks this functionality and it is not part of the official stream
   * wrapper interface. This is a custom implementation for Drupal.
   *
   * @param $mode
   *   Integer value for the permissions. Consult PHP chmod() documentation
   *   for more information.
   *
   * @return
   *   Returns TRUE on success or FALSE on failure.
   */
  public function chmod($mode);

  /**
   * Returns canonical, absolute path of the resource.
   *
   * Implementation placeholder. PHP's realpath() does not support stream
   * wrappers. We provide this as a default so that individual wrappers may
   * implement their own solutions.
   *
   * @return
   *   Returns a string with absolute pathname on success (implemented
   *   by core wrappers), or FALSE on failure or if the registered
   *   wrapper does not provide an implementation.
   */
  public function realpath();

  /**
   * Gets the name of the directory from a given path.
   *
   * This method is usually accessed through drupal_dirname(), which wraps
   * around the normal PHP dirname() function, which does not support stream
   * wrappers.
   *
   * @param $uri
   *   An optional URI.
   *
   * @return
   *   A string containing the directory name, or FALSE if not applicable.
   *
   * @see drupal_dirname()
   */
  public function dirname($uri = NULL);
}
