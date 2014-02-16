<?php

/**
 * @file
 * Drupal stream wrapper implementation for Amazon S3
 *
 * Implements DrupalStreamWrapperInterface to provide an Amazon S3 wrapper with
 * the s3:// prefix.
 */

class AmazonS3StreamWrapper implements DrupalStreamWrapperInterface {

  /**
   * @var String Instance URI referenced as "s3://bucket/key"
   */
  protected $uri;

  /**
   * @var AmazonS3 S3 connection object
   */
  protected $s3 = NULL;

  /**
   * @var string alternative hostname for usage with other API compatible
   * services
   */
  protected $hostname;

  /**
   * @var string S3 bucket name
   */
  protected $bucket;

  /**
   * @var string Domain we use to access files over http
   */
  protected $domain = NULL;

  /**
   * @var int Current read/write position
   */
  protected $position = 0;

  /**
   * @var int Total size of the object as returned by S3 (Content-length)
   */
  protected $objectSize = 0;

  /**
   * @var string Object read/write buffer, typically a file
   */
  protected $buffer = NULL;

  /**
   * @var boolean Whether $buffer is active as a write buffer
   */
  protected $bufferWrite = FALSE;

  /**
   * @var int Buffer length
   */
  protected $bufferLength = 0;

  /**
   * @var array directory listing
   */
  protected $dir = array();

  /**
   * @var array Default map for determining file mime types
   */
  protected static $mapping = NULL;

  /**
   * @var boolean Whether local file metadata caching is on
   */
  protected $caching = FALSE;

  /**
   * @var array Map for files that should be delivered through HTTPS.
   **/
  protected $https = array();

  /**
   * @var array Map for files that should be delivered with a torrent URL.
   */
  protected $torrents = array();

  /**
   * @var array Map for files that should have their Content-disposition header
   * set to force "save as".
   */
  protected $saveAs = array();

  /**
   * @var array Map for files that should have a URL will be created that times
   * out in a designated number of seconds.
   */
  protected $presignedUrls = array();

  /**
   * @var array Map for files that should be saved with Reduced Redundancy
   * Storage.
   */
  protected $rrs = array();

  /**
   * @var bool Whether or not to deliver URLs through CloudFront.
   */
  protected $cloudfront = FALSE;

  /**
   * @var AmazonCF connection object
   */
  protected $cf = NULL;

  /**
   * Object constructor.
   */
  public function __construct() {
    $this->hostname = variable_get('amazons3_hostname', '');
    $this->bucket = $bucket = variable_get('amazons3_bucket', '');

    // CNAME support for customising S3 URLs.
    if (variable_get('amazons3_cname', 0)) {
      $domain = variable_get('amazons3_domain', '');
      if (strlen($domain) > 0) {
        $this->domain = '//' . $domain;
      }
      else {
        $this->domain = '//' . $this->bucket;
      }

      $this->cloudfront = variable_get('amazons3_cloudfront', TRUE);
    }
    else {
      $this->domain = '//' . $this->bucket . '.s3.amazonaws.com';
    }

    // Check whether local file caching is turned on.
    if (variable_get('amazons3_cache', TRUE)) {
      $this->caching = TRUE;
    }

    // HTTPS list.
    $https = explode("\n", variable_get('amazons3_https', ''));
    $https = array_map('trim', $https);
    $https = array_filter($https, 'strlen');
    $this->https = $https;

    // Torrent list.
    $torrents = explode("\n", variable_get('amazons3_torrents', ''));
    $torrents = array_map('trim', $torrents);
    $torrents = array_filter($torrents, 'strlen');
    $this->torrents = $torrents;

    // Presigned url-list.
    $presigned_urls = explode("\n", variable_get('amazons3_presigned_urls', ''));
    $presigned_urls = array_map('trim', $presigned_urls);
    $presigned_urls = array_filter($presigned_urls, 'strlen');
    $this->presignedUrls = array();
    foreach ($presigned_urls as $presigned_url) {
      // Check for an explicit key.
      $matches = array();
      if (preg_match('/(.*)\|(.*)/', $presigned_url, $matches)) {
        $this->presignedUrls[$matches[2]] = $matches[1];
      }
      else {
        $this->presignedUrls[$presigned_url] = 60;
      }
    }

    // Force "save as" list.
    $saveas = explode("\n", variable_get('amazons3_saveas', ''));
    $saveas = array_map('trim', $saveas);
    $saveas  = array_filter($saveas, 'strlen');
    $this->saveAs  = $saveas;

    // Reduced Redundancy Storage.
    $rrs = explode("\n", variable_get('amazons3_rrs', ''));
    $rrs = array_map('trim', $rrs);
    $rrs  = array_filter($rrs, 'strlen');
    $this->rrs  = $rrs;

  }


  /**
   * Sets the stream resource URI.
   *
   * URIs are formatted as "s3://bucket/key"
   *
   * @return string
   *   Returns the current URI of the instance.
   */
  public function setUri($uri) {
    $this->uri = $uri;
  }

  /**
   * Returns the stream resource URI.
   *
   * URIs are formatted as "s3://bucket/key"
   *
   * @return string
   *   Returns the current URI of the instance.
   */
  public function getUri() {
    return $this->uri;
  }

  /**
   * Returns a web accessible URL for the resource.
   *
   * In the format http://mybucket.amazons3.com/myfile.jpg
   *
   * @return string
   *   Returns a string containing a web accessible URL for the resource.
   */
  public function getExternalUrl() {

    // Image styles support
    // Delivers the first request to an image from the private file system
    // otherwise it returns an external URL to an image that has not been
    // created yet.
    $path = explode('/', $this->getLocalPath());
    if ($path[0] === 'styles') {
      if (!$this->_amazons3_get_object($this->uri, $this->caching)) {
        array_shift($path);
        return url('system/files/styles/' . implode('/', $path), array('absolute' => TRUE));
      }
    }

    $local_path = $this->getLocalPath();

    $info = array(
      'download_type' => 'http',
      'https' => FALSE,
      'presigned_url' => FALSE,
      'presigned_url_timeout' => 60,
      'response' => array(),
    );

    // Allow other modules to change the download link type.
    $info = array_merge($info, module_invoke_all('amazons3_url_info', $local_path, $info));

    // UI overrides.
    // HTTPS.
    foreach ($this->https as $path) {
      if ($path === '*' || preg_match('#' . strtr($path, '#', '\#') . '#', $local_path)) {
        $info['https'] = TRUE;
        break;
      }
    }
    // Torrent URLs.
    foreach ($this->torrents as $path) {
      if ($path === '*' || preg_match('#' . strtr($path, '#', '\#') . '#', $local_path)) {
        $info['download_type'] = 'torrent';
        break;
      }
    }
    // Presigned URLs.
    foreach ($this->presignedUrls as $path => $timeout) {
      if ($path === '*' || preg_match('#' . strtr($path, '#', '\#') . '#', $local_path)) {
        $info['presigned_url'] = TRUE;
        $info['presigned_url_timeout'] = $timeout;
        break;
      }
    }
    // Save as.
    foreach ($this->saveAs as $path) {
      if ($path === '*' || preg_match('#' . strtr($path, '#', '\#') . '#', $local_path)) {
        $info['response']['content-disposition'] = 'attachment; filename=' . basename($local_path);
        break;
      }
    }

    $timeout = $info['presigned_url'] ? time() + $info['presigned_url_timeout'] : 0;
    $torrent = $info['download_type'] === 'torrent' ? TRUE : FALSE;
    $response = is_array($info['response']) ? $info['response'] : array();

    // Generate the URL.
    $url = $this->domain . '/' . $local_path;

    if ($info['presigned_url'] && $this->cloudfront) {
      $url = $this->getCF()->get_private_object_url($this->domain, $local_path, $timeout, array('Secure' => $info['https']));
    }
    elseif ($info['presigned_url'] || $info['download_type'] !== 'http' || !empty($info['response'])) {
      $url = $this->getS3()->get_object_url(
        $this->bucket,
        $local_path,
        $timeout,
        array(
          'https' => $info['https'],
          'torrent' => $torrent,
          'response' => $response,
        )
      );
    }
    elseif ($info['https']) {
      $url = 'https:' . $url;
    }

    return $url;
  }

  /**
   * Determine a file's media type.
   *
   * Uses Drupal's mimetype mappings. Returns 'application/octet-stream' if
   * no match is found.
   *
   * @return string
   *   Returns a string representing the file's MIME type
   */
  public static function getMimeType($uri, $mapping = NULL) {

    // Load the default file map.
    if (!isset(self::$mapping)) {
      include_once DRUPAL_ROOT . '/includes/file.mimetypes.inc';
      self::$mapping = file_mimetype_mapping();
    }

    $extension = '';
    $file_parts = explode('.', basename($uri));

    // Remove the first part: a full filename should not match an extension.
    array_shift($file_parts);

    // Iterate over the file parts, trying to find a match.
    // For my.awesome.image.jpeg, we try:
    // jpeg
    // image.jpeg, and
    // awesome.image.jpeg
    while ($additional_part = array_pop($file_parts)) {
      $extension = strtolower($additional_part . ($extension ? '.' . $extension : ''));
      if (isset(self::$mapping['extensions'][$extension])) {
        return self::$mapping['mimetypes'][self::$mapping['extensions'][$extension]];
      }
    }

    return 'application/octet-stream';
  }

  /**
   * Changes permissions of the resource.
   *
   * This doesn't do anything for the moment so just returns TRUE;
   *
   * @param int $mode
   *   Integer value for the permissions. Consult PHP chmod() documentation
   *   for more information.
   *
   * @return bool
   *   Returns TRUE.
   */
  public function chmod($mode) {
    $this->assertConstructorCalled();
    return TRUE;
  }

  /**
   * Returns canonical, absolute path of the resource.
   *
   * @return bool
   *   Returns FALSE as this wrapper does not provide an implementation.
   */
  public function realpath() {
    return FALSE;
  }

  /**
   * Gets the name of the directory from a given path.
   *
   * This method is usually accessed through drupal_dirname(), which wraps
   * around the normal PHP dirname() function, which does not support stream
   * wrappers.
   *
   * @param string $uri
   *   An optional URI.
   *
   * @return string
   *   A string containing the directory name, or FALSE if not applicable.
   *
   * @see drupal_dirname()
   */
  public function dirname($uri = NULL) {
    list($scheme, $target) = explode('://', $uri, 2);
    $target  = $this->getTarget($uri);
    $dirname = dirname($target);

    if ($dirname === '.') {
      $dirname = '';
    }

    return $scheme . '://' . $dirname;
  }

  /**
   * Support for fopen(), file_get_contents(), file_put_contents() etc.
   *
   * @param string $uri
   *   A string containing the URI to the file to open.
   * @param string $mode
   *   The file mode ("r", "wb" etc.).
   * @param int $options
   *   A bit mask of STREAM_USE_PATH and STREAM_REPORT_ERRORS.
   * @param string $opened_path
   *   A string containing the path actually opened.
   *
   * @return bool
   *   Returns TRUE if file was opened successfully.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-open.php
   */
  public function stream_open($uri, $mode, $options, &$opened_path) {
    $this->uri = $uri;

    // If this stream is being opened for writing, clear the object buffer.
    // Return true as we'll create the object on fflush call.
    if (strpbrk($mode, 'wax')) {
      $this->clearBuffer();
      $this->write_buffer = TRUE;
      return TRUE;
    }
    $metadata = $this->_amazons3_get_object($uri, $this->caching);
    if ($metadata) {
      $this->clearBuffer();
      $this->write_buffer = FALSE;
      $this->objectSize = $metadata['filesize'];
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Support for fclose().
   *
   * Clears the object buffer and returns TRUE
   *
   * @return bool
   *   TRUE
   *
   * @see http://php.net/manual/en/streamwrapper.stream-close.php
   */
  public function stream_close() {
    $this->clearBuffer();
    return TRUE;
  }

  /**
   * Support for flock().
   *
   * @param string $operation
   *   One of the following:
   *   - LOCK_SH to acquire a shared lock (reader).
   *   - LOCK_EX to acquire an exclusive lock (writer).
   *   - LOCK_UN to release a lock (shared or exclusive).
   *   - LOCK_NB if you don't want flock() to block while locking (not
   *     supported on Windows).
   *
   * @return bool
   *   returns TRUE if lock was successful
   *
   * @see http://php.net/manual/en/streamwrapper.stream-lock.php
   */
  public function stream_lock($operation) {
    return FALSE;
  }

  /**
   * Support for fread(), file_get_contents() etc.
   *
   * @param int $count
   *   Maximum number of bytes to be read.
   *
   * @return string
   *   The string that was read, or FALSE in case of an error.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-read.php
   */
  public function stream_read($count) {
    // Make sure that count doesn't exceed object size.
    if ($count + $this->position > $this->objectSize) {
      $count = $this->objectSize - $this->position;
    }
    $data = '';
    if ($count > 0) {
      $range_end = $this->position + $count - 1;
      if ($range_end > $this->bufferLength) {
        $opts = array(
          'range' => $this->position . '-' . $range_end,
        );
        $response = $this->getS3()->get_object($this->bucket, $this->getLocalPath($this->uri), $opts);
        if ($response->isOK()) {
          $this->buffer .= $response->body;
          $this->bufferLength += strlen($response->body);
        }
      }
      $data = substr($response->body, 0, min($count, $this->objectSize));
      $this->position += strlen($data);
    }
    return $data;
  }

  /**
   * Support for fwrite(), file_put_contents() etc.
   *
   * @param string $data
   *   The string to be written.
   *
   * @return int
   *   The number of bytes written.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-write.php
   */
  public function stream_write($data) {
    $data_length = strlen($data);
    $this->buffer .= $data;
    $this->bufferLength += $data_length;
    $this->position += $data_length;

    return $data_length;
  }

  /**
   * Support for feof().
   *
   * @return bool
   *   TRUE if end-of-file has been reached.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-eof.php
   */
  public function stream_eof() {
    if (!$this->uri) {
      return TRUE;
    }

    return ($this->position >= $this->objectSize);
  }

  /**
   * Support for fseek().
   *
   * @param int $offset
   *   The byte offset to got to.
   * @param string $whence
   *   SEEK_SET, SEEK_CUR, or SEEK_END.
   *
   * @return bool
   *   TRUE on success.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-seek.php
   */
  public function stream_seek($offset, $whence) {
    switch ($whence) {
      case SEEK_CUR:
        // Set position to current location plus $offset.
        $new_position = $this->position + $offset;
        break;

      case SEEK_END:
        // Set position to eof plus $offset.
        $new_position = $this->objectSize + $offset;
        break;

      case SEEK_SET:
      default:
        // Set position equal to $offset.
        $new_position = $offset;
        break;
    }

    $ret = ($new_position >= 0 && $new_position <= $this->objectSize);
    if ($ret) {
      $this->position = $new_position;
    }
    return $ret;
  }

  /**
   * Support for fflush(). Flush current cached stream data to storage.
   *
   * @return bool
   *   TRUE if data was successfully stored (or there was no data to store).
   *
   * @see http://php.net/manual/en/streamwrapper.stream-flush.php
   */
  public function stream_flush() {
    if ($this->write_buffer) {
      // Set the storage type.
      $s3_storage_type = AmazonS3::STORAGE_STANDARD;
      $local_path = $this->getLocalPath();
      foreach ($this->rrs as $path) {
        if (preg_match('#' . strtr($path, '#', '\#') . '#', $local_path)) {
          $s3_storage_type = AmazonS3::STORAGE_REDUCED;
          break;
        }
      }

      // Set the metadata.
      $headers = array();
      $headers = array_merge($headers, module_invoke_all('amazons3_save_headers', $local_path, $headers));

      // Save the object.
      $response = $this->getS3()->create_object($this->bucket, $local_path, array(
        'body' => $this->buffer,
        'acl' => AmazonS3::ACL_PUBLIC,
        'contentType' => AmazonS3StreamWrapper::getMimeType($this->uri),
        'storage' => $s3_storage_type,
        'headers' => $headers,
      ));
      if ($response->isOK()) {
        return TRUE;
      }
    }
    $this->clearBuffer();
    return FALSE;
  }

  /**
   * Support for ftell().
   *
   * @return int
   *   The current offset in bytes from the beginning of file.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-tell.php
   */
  public function stream_tell() {
    return $this->position;
  }

  /**
   * Support for fstat().
   *
   * @return array
   *   An array with file status, or FALSE in case of an error - see fstat()
   *   for a description of this array.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-stat.php
   */
  public function stream_stat() {
    return $this->_stat();
  }

  /**
   * Support for unlink().
   *
   * @param string $uri
   *   A string containing the uri to the resource to delete.
   *
   * @return bool
   *   TRUE if resource was successfully deleted.
   *
   * @see http://php.net/manual/en/streamwrapper.unlink.php
   */
  public function unlink($uri) {
    $this->assertConstructorCalled();
    $response = $this->getS3()->delete_object($this->bucket, $this->getLocalPath($uri));
    if ($response->isOK()) {
      // Delete from cache.
      db_delete('amazons3_file')
        ->condition('uri', $uri)
        ->execute();
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Support for rename().
   *
   * If $to_uri exists, this file will be overwritten. This behavior is
   * identical to the PHP rename() function.
   *
   * @param string $from_uri
   *   The uri to the file to rename.
   * @param string $to_uri
   *   The new uri for file.
   *
   * @return bool
   *   TRUE if file was successfully renamed.
   *
   * @see http://php.net/manual/en/streamwrapper.rename.php
   */
  public function rename($from_uri, $to_uri) {
    $this->assertConstructorCalled();
    $from = $this->getLocalPath($from_uri);
    $to = $this->getLocalPath($to_uri);
    $s3 = $this->getS3();

    $response = $s3->copy_object(
      array('bucket' => $this->bucket, 'filename' => $from),
      array('bucket' => $this->bucket, 'filename' => $to),
      array('acl' => AmazonS3::ACL_PUBLIC)
    );

    // Check the response and then remove the original.
    return $response->isOK() && $this->unlink($from_uri);
  }

  /**
   * Returns the local writable target of the resource within the stream.
   *
   * This function should be used in place of calls to realpath() or similar
   * functions when attempting to determine the location of a file. While
   * functions like realpath() may return the location of a read-only file, this
   * method may return a URI or path suitable for writing that is completely
   * separate from the URI used for reading.
   *
   * @param string $uri
   *   Optional URI.
   *
   * @return string
   *   Returns a string representing a location suitable for writing of a file,
   *   or FALSE if unable to write to the file such as with read-only streams.
   */
  protected function getTarget($uri = NULL) {
    if (!isset($uri)) {
      $uri = $this->uri;
    }

    list($scheme, $target) = explode('://', $uri, 2);

    // Remove erroneous leading or trailing forward-slashes and backslashes.
    return trim($target, '\/');
  }

  /**
   * Support for mkdir().
   *
   * @param string $uri
   *   A string containing the URI to the directory to create.
   * @param int $mode
   *   Permission flags - see mkdir().
   * @param int $options
   *   A bit mask of STREAM_REPORT_ERRORS and STREAM_MKDIR_RECURSIVE.
   *
   * @return bool
   *   TRUE if directory was successfully created.
   *
   * @see http://php.net/manual/en/streamwrapper.mkdir.php
   */
  public function mkdir($uri, $mode, $options) {
    $this->assertConstructorCalled();
    $recursive = (bool) ($options & STREAM_MKDIR_RECURSIVE);
    $localpath = $this->getLocalPath($uri);

    // s3 has no concept of directories, but we emulate it by creating an
    // object of size 0 with a trailing "/"
    $response = $this->getS3()->create_object($this->bucket, $localpath . '/', array('body' => ''));
    if ($response->isOk()) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Support for rmdir().
   *
   * @param string $uri
   *   A string containing the URI to the directory to delete.
   * @param int $options
   *   A bit mask of STREAM_REPORT_ERRORS.
   *
   * @return bool
   *   TRUE if directory was successfully removed.
   * @see http://php.net/manual/en/streamwrapper.rmdir.php
   */
  public function rmdir($uri, $options) {
    return $this->amazons3_rmdir($uri, $options);
  }
  /**
   * Helper function for deleting directories.
   *
   * The force flag will perform a recursive delete.
   */
  public function amazons3_rmdir($uri, $options, $force = FALSE) {
    $this->assertConstructorCalled();
    $localpath = $this->getLocalPath($uri);
    $s3 = $this->getS3();

    if ($force) {
      $objects = $s3->get_object_list($this->bucket, array('prefix' => $localpath));
      if (gettype($objects) === 'array' && !empty($objects)) {
        $or = db_or();
        foreach ($objects as $object) {
          $s3->batch()->delete_object($this->bucket, $object);
          // Delete from cache.
          $object_uri = 's3://' . rtrim($object, '/');
          $or->condition('uri', $object_uri, '=');
        }
        db_delete('amazons3_file')->condition($or)->execute();
        $responses = $s3->batch()->send();

        if ($responses->areOK()) {
          return TRUE;
        }
      }
    }
    else {
      $objects = $s3->get_object_list($this->bucket, array('prefix' => $localpath, 'max-keys' => 2));
      if (count($objects) === 1) {
        $response = $this->getS3()->delete_object($this->bucket, $localpath);
        $response2 = $this->getS3()->delete_object($this->bucket, $localpath . '/');
        if ($response->isOK() || $response2->isOK()) {
          // Delete from cache.
          db_delete('amazons3_file')
            ->condition('uri', $uri)
            ->execute();
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Support for stat().
   *
   * @param string $uri
   *   A string containing the URI to get information about.
   * @param int $flags
   *   A bit mask of STREAM_URL_STAT_LINK and STREAM_URL_STAT_QUIET.
   *
   * @return array
   *   An array with file status, or FALSE in case of an error - see fstat()
   *   for a description of this array.
   *
   * @see http://php.net/manual/en/streamwrapper.url-stat.php
   */
  public function url_stat($uri, $flags) {
    $this->assertConstructorCalled();
    return $this->_stat($uri);
  }

  /**
   * Support for opendir().
   *
   * @param string $uri
   *   A string containing the URI to the directory to open.
   * @param array $options
   *   Unknown (parameter is not documented in PHP Manual).
   *
   * @return bool
   *   TRUE on success.
   *
   * @see http://php.net/manual/en/streamwrapper.dir-opendir.php
   */
  public function dir_opendir($uri, $options) {
    $this->assertConstructorCalled();
    if ($uri === NULL) {
      return FALSE;
    }
    elseif (!$this->_amazons3_is_dir($uri)) {
      return FALSE;
    }

    $this->dir = array();
    $path = $this->getLocalPath($uri);
    $truncated = TRUE;
    $marker = '';
    if (strlen($path) === 0) {
      $prefix = $path;
    }
    else {
      $prefix = $path . '/';
    }
    $prefix_length = strlen($prefix);

    while ($truncated) {
      $response = $this->getS3()->list_objects($this->bucket, array(
          'delimiter' => '/',
          'prefix' => $prefix,
          'marker' => urlencode($marker),
      ));
      if ($response->isOK()) {

        $this->dir[] = '.';
        $this->dir[] = '..';

        // Folders.
        if (isset($response->body->CommonPrefixes)) {
          foreach ($response->body->CommonPrefixes as $prefix) {
            $marker = substr($prefix->Prefix, $prefix_length, strlen($prefix->Prefix) - $prefix_length - 1);
            if (strlen($marker) > 0) {
              $this->dir[] = $marker;
            }
          }
        }

        // Files.
        if (isset($response->body->Contents)) {
          $contents = $response->body->to_stdClass()->Contents;
          if (!is_array($contents)) {
            $contents = array($contents);
          }

          foreach ($contents as $content) {
            $key = $content->Key;
            if (substr_compare($key, '/', -1, 1) !== 0) {
              $marker = basename($key);
              $this->dir[] = $marker;
            }
          }
        }

        if (!isset($response->body->IsTruncated) || $response->body->IsTruncated === 'false') {
          $truncated = FALSE;
        }
      }
      else {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Support for readdir().
   *
   * @return string|bool
   *   The next filename, or FALSE if there are no more files in the directory.
   *
   * @see http://php.net/manual/en/streamwrapper.dir-readdir.php
   */
  public function dir_readdir() {
    $filename = current($this->dir);
    if ($filename !== FALSE) {
      next($this->dir);
    }
    return $filename;
  }

  /**
   * Support for rewinddir().
   *
   * @return bool
   *   TRUE on success.
   *
   * @see http://php.net/manual/en/streamwrapper.dir-rewinddir.php
   */
  public function dir_rewinddir() {
    reset($this->dir);
    return TRUE;
  }

  /**
   * Support for closedir().
   *
   * @return bool
   *   TRUE on success.
   *
   * @see http://php.net/manual/en/streamwrapper.dir-closedir.php
   */
  public function dir_closedir() {
    $this->dir = array();
    return TRUE;
  }

  /**
   * Return the local filesystem path.
   *
   * @param string $uri
   *   Optional URI, supplied when doing a move or rename.
   */
  protected function getLocalPath($uri = NULL) {
    if (!isset($uri)) {
      $uri = $this->uri;
    }

    $path  = str_replace('s3://', '', $uri);
    $path = trim($path, '/');
    return $path;
  }

  /**
   * Gets the path that the wrapper is responsible for.
   *
   * @return string
   *   String specifying the path.
   */
  public function getDirectoryPath() {
    return $this->domain;
  }

  /**
   * Flush the object buffers.
   */
  protected function clearBuffer() {
    $this->position = 0;
    $this->objectSize = 0;
    $this->buffer = NULL;
    $this->bufferWrite = FALSE;
    $this->bufferLength = 0;
  }

  /**
   * Get the S3 connection object.
   *
   * @return AmazonS3
   *   S3 connection object (AmazonS3)
   *
   * @see http://docs.amazonwebservices.com/AWSSDKforPHP/latest/#i=AmazonS3
   */
  protected function getS3() {
    if ($this->s3 === NULL) {
      if (!libraries_load('awssdk')) {
        drupal_set_message(t('Unable to load the AWS SDK. Please check you have installed the library correctly and configured your S3 credentials.') . 'error');
      }
      elseif (empty($this->bucket)) {
        drupal_set_message(t('Bucket name not configured.') . 'error');
      }
      else {
        try {
          $this->s3 = new AmazonS3();
          if (!empty($this->hostname)) {
            $this->s3->set_hostname($this->hostname);
            $this->s3->allow_hostname_override(FALSE);
          }
        }
        catch (Exception $e) {
          drupal_set_message(t('There was a problem using S3. @message', array('message' => $e->getMessage())), 'error');
        }
      }
    }
    return $this->s3;
  }

  /**
   * Get the CloudFront connection object.
   *
   * @return AmazonCloudFront
   *   CloudFront connection object.
   *
   * @see http://docs.amazonwebservices.com/AWSSDKforPHP/latest/#i=AmazonCloudFront
   */
  protected function getCF() {
    if ($this->cf === NULL) {
      if (!libraries_load('awssdk')) {
        drupal_set_message(t('Unable to load the AWS SDK. Please check you have installed the library correctly and configured your CloudFront settings.'), 'error');
      }
      elseif (variable_get('amazons3_cname', 0) && variable_get('amazons3_domain', 0)) {
        try {
          $this->cf = new AmazonCloudFront();
        }
        catch (Exception $e) {
          drupal_set_message(t('There was a problem using CloudFront. @message', array('message' => $e->getMessage())), 'error');
        }
      }
    }

    return $this->cf;
  }

  /**
   * Get file status.
   *
   * @return array
   *   An array with file status, or FALSE in case of an error - see fstat()
   *   for a description of this array.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-stat.php
   */
  protected function _stat($uri = NULL) {
    if (!isset($uri)) {
      $uri = $this->uri;
    }
    $metadata = $this->_amazons3_get_object($uri, $this->caching);
    if ($metadata) {
      $stat = array();
      $stat[0] = $stat['dev'] = 0;
      $stat[1] = $stat['ino'] = 0;
      $stat[2] = $stat['mode'] = $metadata['mode'];
      $stat[3] = $stat['nlink'] = 0;
      $stat[4] = $stat['uid'] = 0;
      $stat[5] = $stat['gid'] = 0;
      $stat[6] = $stat['rdev'] = 0;
      $stat[7] = $stat['size'] = 0;
      $stat[8] = $stat['atime'] = 0;
      $stat[9] = $stat['mtime'] = 0;
      $stat[10] = $stat['ctime'] = 0;
      $stat[11] = $stat['blksize'] = 0;
      $stat[12] = $stat['blocks'] = 0;

      if (!$metadata['dir']) {
        if (!isset($metadata['uid']) || !isset($metadata['filesize']) || !isset($metadata['timestamp'])) {
          return FALSE;
        }
        else {
          $stat[4] = $stat['uid'] = $metadata['uid'];
          $stat[7] = $stat['size'] = $metadata['filesize'];
          $stat[8] = $stat['atime'] = $metadata['timestamp'];
          $stat[9] = $stat['mtime'] = $metadata['timestamp'];
          $stat[10] = $stat['ctime'] = $metadata['timestamp'];
        }
      }
      return $stat;
    }
    return FALSE;
  }


  /**
   * Determine whether the $uri is a directory.
   *
   * @param string $uri
   *   A string containing the uri to the resource to check. If none is given
   *   defaults to $this->uri
   *
   * @return bool
   *   TRUE if the resource is a directory
   */
  protected function _amazons3_is_dir($uri = NULL) {
    if ($uri === NULL) {
      $uri = $this->uri;
    }
    if ($uri !== NULL) {
      $path = $this->getLocalPath($uri);
      if (strlen($path) === 0) {
        return TRUE;
      }
      $response = $this->getS3()->list_objects($this->bucket, array(
          'prefix' => $path . '/',
          'max-keys' => 1,
        ));
      if ($response && isset($response->body->Contents->Key)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * CACHING FUNCTIONS
   */

  /**
   * Try to fetch an object from the metadata cache.
   *
   * Otherwise fetch it's info from S3 and populate the cache.
   *
   * @param string $uri
   *   A string containing the uri of the resource to check.
   * @param bool $cache
   *   A boolean representing whether to check the cache for file information.
   *
   * @return array
   *   An array if the $uri exists, otherwise FALSE.
   */
  protected function _amazons3_get_object($uri, $cache = TRUE) {
    if ($uri === 's3://' || $uri === 's3:') {
      $metadata = $this->_amazons3_format_response('/', array(), TRUE);
      return $metadata;
    }
    else {
      $uri = rtrim($uri, '/');
    }

    if ($cache) {
      $metadata = $this->_amazons3_get_cache($uri);
      if ($metadata) {
        return $metadata;
      }
    }

    $is_dir = $this->_amazons3_is_dir($uri);
    $metadata = NULL;
    if ($is_dir) {
      $metadata = $this->_amazons3_format_response($uri, array(), TRUE);
    }
    else {
      $response = $this->getS3()->get_object_metadata($this->bucket, $this->getLocalPath($uri));
      if ($response) {
        $metadata = $this->_amazons3_format_response($uri, $response);
      }
    }
    if (is_array($metadata)) {
      // Save to the cache.
      db_merge('amazons3_file')
        ->key(array('uri' => $metadata['uri']))
        ->fields($metadata)
        ->execute();
      return $metadata;
    }
    return FALSE;
  }

  /**
   * Fetch an object from the local metadata cache.
   *
   * @param string $uri
   *   A string containing the uri of the resource to check.
   *
   * @return array
   *   An array if the $uri is in the cache, otherwise FALSE
   */
  protected function _amazons3_get_cache($uri) {
    // Check cache for existing object.
    $result = db_query("SELECT * FROM {amazons3_file} WHERE uri = :uri", array(
      ':uri' => $uri,
    ));
    $record = $result->fetchAssoc();
    if ($record) {
      return $record;
    }
    return FALSE;
  }

  /**
   * Format returned file information from S3 into an array.
   *
   * @param string $uri
   *   A string containing the uri of the resource to check.
   * @param array $response
   *   An array containing the collective metadata for the Amazon S3 object
   * @param bool $is_dir
   *   A boolean indicating whether this object is a directory.
   *
   * @return array
   *   An array containing formatted metadata
   */
  protected function _amazons3_format_response($uri, $response, $is_dir = FALSE) {
    $metadata = array('uri' => $uri);
    if (isset($response['Size'])) {
      $metadata['filesize'] = $response['Size'];
    }
    if (isset($response['LastModified'])) {
      $metadata['timestamp'] = date('U', strtotime((string) $response['LastModified']));
    }
    if (isset($response['Owner']['ID'])) {
      $metadata['uid'] = (string) $response['Owner']['ID'];
    }
    if ($is_dir) {
      $metadata['dir'] = 1;
      // S_IFDIR indicating directory.
      $metadata['mode'] = 0040000;
      $metadata['mode'] |= 0777;
    }
    else {
      $metadata['dir'] = 0;
      // S_IFREG indicating file.
      $metadata['mode'] = 0100000;
      // Everything is writeable.
      $metadata['mode'] |= 0777;
    }
    return $metadata;
  }

  /**
   * Assert that the constructor has been called, call it if not.
   *
   * Due to PHP bug #40459, the constructor of this class isn't always called
   * for some of the methods. This private method calls the constructor if
   * it hasn't been called before.
   *
   * @see https://bugs.php.net/bug.php?id=40459
   */
  protected function assertConstructorCalled() {
    if ($this->domain === NULL) {
      $this->__construct();
    }
  }
}
