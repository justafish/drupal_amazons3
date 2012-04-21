<?php

/**
 * @file
 * Drupal stream wrapper implementation for Amazon S3
 *
 * Implements DrupalStreamWrapperInterface to provide an Amazon S3 wrapper with
 * the s3:// prefix
 */
class AmazonS3StreamWrapper implements DrupalStreamWrapperInterface {

  /**
   * @var String Instance URI referenced as "s3://bucket/key"
   */
  protected $uri;

  /**
   * @var AmazonS3 S3 connection object
   */
  protected $s3 = null;

  /**
   * @var string S3 bucket name
   */
  protected $bucket;

  /**
   * @var string Domain we use to access files over http
   */
  protected $domain;

  /**
   * @var int Current read/write position
   */
  protected $position = 0;

  /**
   * @var int Total size of the object as returned by S3 (Content-length)
   */
  protected $object_size = 0;

  /**
   * @var string Object read/write buffer, typically a file
   */
  protected $buffer = null;

  /**
   * @var boolean Whether $buffer is active as a write buffer
   */
  protected $buffer_write = false;

  /**
   * @var int Buffer length
   */
  protected $buffer_length = 0;

  /**
   * @var array directory listing
   */
  protected $dir = array();

  /**
   * @var array Default map for determining file mime types
   */
  protected static $mapping = null;

  /**
   * @var boolean Whether local file metadata caching is on
   */
  protected $caching = FALSE;

  /**
   * @var array Map for files that should be delivered with a torrent URL.
   */
  protected $torrents = array();

  /**
   * @var array Map for files that should have their Content-disposition header
   * set to force "save as".
   */
  protected $saveas = array();

  /**
   * @var array Map for files that should have a URL will be created that times
   * out in a designated number of seconds.
   */
  protected $presigned_urls = array();

  /**
   * Object constructor
   *
   * Sets the bucket name
   */
  public function __construct() {
    $this->bucket = $bucket = variable_get('amazons3_bucket', '');

    // CNAME support for customising S3 URLs
    if (variable_get('amazons3_cname', 0)) {
      $domain = variable_get('amazons3_domain', '');
      if(strlen($domain) > 0) {
        $this->domain = 'http://' . $domain;
      }
      else {
        $this->domain = 'http://' . $this->bucket;
      }
    }
    else {
      $this->domain = 'http://' . $this->bucket . '.s3.amazonaws.com';
    }

    // Check whether local file caching is turned on
    if (variable_get('amazons3_cache', FALSE)) {
      $this->caching = TRUE;
    }

    // Torrent list
    $torrents = explode("\n", variable_get('amazons3_torrents', ''));
    $torrents = array_map('trim', $torrents);
    $torrents = array_filter($torrents, 'strlen');
    $this->torrents = $torrents;


    // Presigned url-list
    $presigned_urls = explode("\n", variable_get('amazons3_presigned_urls', ''));
    $presigned_urls = array_map('trim', $presigned_urls);
    $presigned_urls = array_filter($presigned_urls, 'strlen');
    $this->presigned_urls = array();
    foreach ($presigned_urls as $presigned_url) {
      // Check for an explicit key.
      $matches = array();
      if (preg_match('/(.*)\|(.*)/', $presigned_url, $matches)) {
        $this->presigned_urls[$matches[2]] = $matches[1];
      }
      else {
        $this->presigned_urls[$presigned_url] = 60;
      }
    }

    // Force "save as" list
    $saveas = explode("\n", variable_get('amazons3_saveas', ''));
    $saveas = array_map('trim', $saveas );
    $saveas  = array_filter($saveas , 'strlen');
    $this->saveas  = $saveas;

  }


  /**
   * Sets the stream resource URI.
   *
   * URIs are formatted as "s3://bucket/key"
   *
   * @return
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
   * @return
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
   * @return
   *   Returns a string containing a web accessible URL for the resource.
   */
  public function getExternalUrl() {

    // Image styles support
    // Delivers the first request to an image from the private file system
    // otherwise it returns an external URL to an image that has not been
    // created yet
    $path = explode('/', $this->getLocalPath());
    if ($path[0] == 'styles') {
      if (!$this->_amazons3_get_object($this->uri, $this->caching)) {
        array_shift($path);
        return url('system/files/styles/' . implode('/', $path), array('absolute' => true));
      }
    }

    $local_path = $this->getLocalPath();

    $info = array(
      'download_type' => 'http',
      'presigned_url' => FALSE,
      'presigned_url_timeout' => 60,
      'response' => array(),
    );

    // Allow other modules to change the download link type.
    $info = array_merge($info, module_invoke_all('amazons3_url_info', $local_path, $info));

    // UI overrides
    // Torrent URLs
    if ($info['download_type'] != 'torrent') {
      foreach ($this->torrents as $path) {
        if (preg_match('#' . strtr($path, '#', '\#') . '#', $local_path)) {
          $info['download_type'] = 'torrent';
          break;
        }
      }
    }
    // Presigned URLs
    if (!$info['presigned_url']) {
      foreach ($this->presigned_urls as $path => $timeout) {
        if (preg_match('#' . strtr($path, '#', '\#') . '#', $local_path)) {
          $info['presigned_url'] = TRUE;
          $info['presigned_url_timeout'] = $timeout;
          break;
        }
      }
    }
    // Save as
    if ($info['download_type'] != 'torrent') {
      foreach ($this->saveas as $path) {
        if (preg_match('#' . strtr($path, '#', '\#') . '#', $local_path)) {
          $info['response']['content-disposition'] = 'attachment; filename=' . basename($local_path);
          break;
        }
      }
    }

    $timeout = ($info['presigned_url']) ? time() + $info['presigned_url_timeout'] : 0;
    $torrent = ($info['download_type'] == 'torrent') ? TRUE : FALSE;
    $response = ($info['presigned_url']) ? $info['response'] : array();
    if ($info['presigned_url'] || $info['download_type'] != 'http' || !empty($info['response'])) {
      $url = $this->getS3()->get_object_url($this->bucket, $local_path, $timeout, array('torrent' => $torrent, 'response' => $response));
      return $url;
    }

    $url = $this->domain . '/' . $local_path;
    return $url;
  }

  /**
   * Determine a file's media type
   *
   * Uses Drupal's mimetype mappings. Returns 'application/octet-stream' if
   * no match is found.
   *
   *  @return
   *   Returns a string representing the file's MIME type
   */
  public static function getMimeType($uri, $mapping = NULL) {

    // Load the default file map
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
    //   - jpeg
    //   - image.jpeg, and
    //   - awesome.image.jpeg
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
   * This doesn't do anything for the moment so just returns FALSE;
   *
   * @param $mode
   *   Integer value for the permissions. Consult PHP chmod() documentation
   *   for more information.
   *
   * @return
   *   Returns TRUE on success or FALSE on failure.
   */
  public function chmod($mode) {
  /**  $modes = str_split($mode);
    if($modes[0] == '0') {
      array_shift($modes);
    }
    if(count($modes) != 3) {
      return FALSE;
    }

    if(intval($modes[2]) >= 4) {
      $acl = AmazonS3::ACL_PUBLIC;
    }
    else {
      $acl = AmazonS3::ACL_PRIVATE;
    }

    $local_path = $this->getLocalPath();
    if($this->_is_dir() && (substr($local_path, -1) != '/')) {
      $local_path .= '/';
    }

    $response = $this->getS3()->set_object_acl($this->bucket, $local_path, $acl);
    return $response->isOK();**/
    return TRUE;
  }

  /**
   * Returns canonical, absolute path of the resource.
   *
   * @return
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
   * @param $uri
   *   An optional URI.
   *
   * @return
   *   A string containing the directory name, or FALSE if not applicable.
   *
   * @see drupal_dirname()
   */
  public function dirname($uri = NULL) {
   list($scheme, $target) = explode('://', $uri, 2);
   $target  = $this->getTarget($uri);
   $dirname = dirname($target);

   if ($dirname == '.') {
     $dirname = '';
   }

   return $scheme . '://' . $dirname;
  }

  /**
   * Support for fopen(), file_get_contents(), file_put_contents() etc.
   *
   * @param $uri
   *   A string containing the URI to the file to open.
   * @param $mode
   *   The file mode ("r", "wb" etc.).
   * @param $options
   *   A bit mask of STREAM_USE_PATH and STREAM_REPORT_ERRORS.
   * @param $opened_path
   *   A string containing the path actually opened.
   *
   * @return
   *   Returns TRUE if file was opened successfully.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-open.php
   */
  public function stream_open($uri, $mode, $options, &$opened_path) {
    $this->uri = $uri;

    // if this stream is being opened for writing, clear the object buffer
    // Return true as we'll create the object on fflush call
    if (strpbrk($mode, 'wax')) {
      $this->clearBuffer();
      $this->write_buffer = TRUE;
      return TRUE;
    }
    $metadata = $this->_amazons3_get_object($uri, $this->caching);
    if ($metadata) {
      $this->clearBuffer();
      $this->write_buffer = false;
      $this->object_size = $metadata['filesize'];
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Support for fclose().
   *
   * Clears the object buffer and returns TRUE
   *
   * @return
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
   * @param $operation
   *   One of the following:
   *   - LOCK_SH to acquire a shared lock (reader).
   *   - LOCK_EX to acquire an exclusive lock (writer).
   *   - LOCK_UN to release a lock (shared or exclusive).
   *   - LOCK_NB if you don't want flock() to block while locking (not
   *     supported on Windows).
   *
   * @return
   *   returns TRUE if lock was successful
   *
   * @see http://php.net/manual/en/streamwrapper.stream-lock.php
   */
  public function stream_lock($operation) {
    return false;
  }

  /**
   * Support for fread(), file_get_contents() etc.
   *
   * @param $count
   *   Maximum number of bytes to be read.
   *
   * @return
   *   The string that was read, or FALSE in case of an error.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-read.php
   */
  public function stream_read($count) {
    $opts = array(
      'range' => $this->position . '-' . min($this->object_size, $this->position + $count - 1)
    );
    $response = $this->getS3()->get_object($this->bucket, $this->getLocalPath($this->uri), $opts);
    if ($response->isOK()) {
      $this->position += strlen($response->body);
      return $response->body;
    }
    return FALSE;
  }

  /**
   * Support for fwrite(), file_put_contents() etc.
   *
   * @param $data
   *   The string to be written.
   *
   * @return
   *   The number of bytes written (integer).
   *
   * @see http://php.net/manual/en/streamwrapper.stream-write.php
   */
  public function stream_write($data) {
    $data_length = strlen($data);
    $this->buffer .= $data;
    $this->buffer_length += $data_length;
    $this->position += $data_length;

    return $data_length;
  }

  /**
   * Support for feof().
   *
   * @return
   *   TRUE if end-of-file has been reached.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-eof.php
   */
  public function stream_eof() {
    if (!$this->uri) {
        return true;
    }

    return ($this->position >= $this->object_size);
  }

  /**
   * Support for fseek().
   *
   * @param $offset
   *   The byte offset to got to.
   * @param $whence
   *   SEEK_SET, SEEK_CUR, or SEEK_END.
   *
   * @return
   *   TRUE on success.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-seek.php
   */
  public function stream_seek($offset, $whence) {
    switch($whence) {
      case SEEK_CUR:
        // Set position to current location plus $offset
        $new_position = $this->position + $offset;
        break;
      case SEEK_END:
        // Set position to eof plus $offset
        $new_position = $this->object_size + $offset;
        break;
      case SEEK_SET:
      default:
        // Set position equal to $offset
        $new_position = $offset;
        break;
    }

    $ret = ($new_position >= 0 && $new_position <= $this->object_size);
    if ($ret) {
      $this->position = $new_position;
    }
    return $ret;
  }

  /**
   * Support for fflush(). Flush current cached stream data to storage.
   *
   * @return
   *   TRUE if data was successfully stored (or there was no data to store).
   *
   * @see http://php.net/manual/en/streamwrapper.stream-flush.php
   */
  public function stream_flush() {
    if($this->write_buffer) {
      $response = $this->getS3()->create_object($this->bucket, $this->getLocalPath(), array(
        'body' => $this->buffer,
        'acl' => AmazonS3::ACL_PUBLIC,
        'contentType' => AmazonS3StreamWrapper::getMimeType($this->uri),
      ));
      if($response->isOK()) {
        return TRUE;
      }
    }
    $this->clearBuffer();
    return FALSE;
  }

  /**
   * Support for ftell().
   *
   * @return
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
   * @return
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
   * @param $uri
   *   A string containing the uri to the resource to delete.
   *
   * @return
   *   TRUE if resource was successfully deleted.
   *
   * @see http://php.net/manual/en/streamwrapper.unlink.php
   */
  public function unlink($uri) {
    $response = $this->getS3()->delete_object($this->bucket, $this->getLocalPath($uri));
    if($response->isOK()) {
      // Delete from cache
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
   * @param $from_uri,
   *   The uri to the file to rename.
   * @param $to_uri
   *   The new uri for file.
   *
   * @return
   *   TRUE if file was successfully renamed.
   *
   * @see http://php.net/manual/en/streamwrapper.rename.php
   */
  public function rename($from_uri, $to_uri) {
    $from = $this->getLocalPath($from_uri);
    $to = $this->getLocalPath($to_uri);

    /**
     * @todo Finish this...
    if ($this->getS3()->if_object_exists($this->bucket, $from) && !$this->getS3()->if_object_exists($this->bucket, $to)) {
      // get a lock
    }
    **/

    return FALSE;
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
   * @param $uri
   *   Optional URI.
   *
   * @return
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
   * @param $uri
   *   A string containing the URI to the directory to create.
   * @param $mode
   *   Permission flags - see mkdir().
   * @param $options
   *   A bit mask of STREAM_REPORT_ERRORS and STREAM_MKDIR_RECURSIVE.
   *
   * @return
   *   TRUE if directory was successfully created.
   *
   * @see http://php.net/manual/en/streamwrapper.mkdir.php
   */
  public function mkdir($uri, $mode, $options) {

    $recursive = (bool) ($options & STREAM_MKDIR_RECURSIVE);
    $localpath = $this->getLocalPath($uri);

    // s3 has no concept of directories, but we emulate it by creating an
    // object of size 0 with a trailing "/"
    $response = $this->getS3()->create_object($this->bucket, $localpath . '/', array('body' => ''));
    if($response->isOk()) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Support for rmdir().
   *
   * @param $uri
   *   A string containing the URI to the directory to delete.
   * @param $options
   *   A bit mask of STREAM_REPORT_ERRORS.
   *
   * @return
   *   TRUE if directory was successfully removed.
   *
   * @see http://php.net/manual/en/streamwrapper.rmdir.php
   */
  public function rmdir($uri, $options) {
    $localpath = $this->getLocalPath($uri);
    $s3 = $this->getS3();

    $objects = $s3->get_object_list($this->bucket, array('prefix' => $localpath));
    if (gettype($objects) === 'array') {
      $or = db_or();
      foreach($objects as $object) {
        $s3->batch()->delete_object($this->bucket, $object);
        // Delete from cache
        $object_uri = 's3://' . rtrim($object,'/');
        $or->condition('uri', $object_uri, '=');
      }
      db_delete('amazons3_file')->condition($or)->execute();
      $responses = $s3->batch()->send();

      if($responses->areOK()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Support for stat().
   *
   * @param $uri
   *   A string containing the URI to get information about.
   * @param $flags
   *   A bit mask of STREAM_URL_STAT_LINK and STREAM_URL_STAT_QUIET.
   *
   * @return
   *   An array with file status, or FALSE in case of an error - see fstat()
   *   for a description of this array.
   *
   * @see http://php.net/manual/en/streamwrapper.url-stat.php
   */
  public function url_stat($uri, $flags) {
    return $this->_stat($uri);
  }

  /**
   * Support for opendir().
   *
   * @param $uri
   *   A string containing the URI to the directory to open.
   * @param $options
   *   Unknown (parameter is not documented in PHP Manual).
   *
   * @return
   *   TRUE on success.
   *
   * @see http://php.net/manual/en/streamwrapper.dir-opendir.php
   */
  public function dir_opendir($uri, $options) {
    if ($uri == null) {
      return FALSE;
    }
    else if(!$this->_is_dir($uri)) {
      return FALSE;
    }

    $this->dir = array();
    $path = $this->getLocalPath($uri);
    $truncated = TRUE;
    $marker = '';
    if(strlen($path) == 0) {
      $prefix = $path;
    }
    else {
      $prefix = $path . '/';
    }
    $prefix_length = strlen($prefix);

    while($truncated) {
      $response = $this->getS3()->list_objects($this->bucket, array(
          'delimiter' => '/',
          'prefix' => $prefix,
          'marker' => urlencode($marker),
      ));
      if ($response->isOK()) {

        $this->dir[] = '.';
        $this->dir[] = '..';

        // Folders
        if (isset($response->body->CommonPrefixes)) {
          foreach($response->body->CommonPrefixes as $prefix) {
            $marker = substr($prefix->Prefix, $prefix_length, strlen($prefix->Prefix) - $prefix_length - 1);
            if(strlen($marker) > 0) {
              $this->dir[] = $marker;
            }
          }
        }

        // Files
        if(isset($response->body->Contents)) {
          $contents = $response->body->to_stdClass()->Contents;
          if (!is_array($contents)) {
            $contents = array($contents);
          }

          foreach($contents as $content) {
            $key = $content->Key;
            if(substr_compare($key, '/', -1, 1) !== 0) {
              $marker = basename($key);
              $this->dir[] = $marker;
            }
          }
        }

        if(!isset($response->body->IsTruncated) || $response->body->IsTruncated == 'false') {
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
   * @return
   *   The next filename, or FALSE if there are no more files in the directory.
   *
   * @see http://php.net/manual/en/streamwrapper.dir-readdir.php
   */
  public function dir_readdir() {
    $filename = current($this->dir);
    if ($filename !== false) {
        next($this->dir);
    }
    return $filename;
  }

  /**
   * Support for rewinddir().
   *
   * @return
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
   * @return
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
   * @param $uri
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
   * @return
   *   String specifying the path.
   */
  public function getDirectoryPath() {
    return $this->domain;
  }

  /**
   * Flush the object buffers
   */
  protected function clearBuffer() {
    $this->position = 0;
    $this->object_size = 0;
    $this->buffer = null;
    $this->buffer_write = false;
    $this->buffer_length = 0;
  }

  /**
   * Get the S3 connection object
   *
   * @return
   *   S3 connection object (AmazonS3)
   *
   * @see http://docs.amazonwebservices.com/AWSSDKforPHP/latest/#i=AmazonS3
   */
  protected function getS3() {
    if($this->s3 == null) {
      $bucket = variable_get('amazons3_bucket', '');

      if(!libraries_load('awssdk') && !isset($bucket)) {
        drupal_set_message('Unable to load the AWS SDK. Please check you have installed the library correctly and configured your S3 credentials.'. 'error');
      }
      else if(!isset($bucket)) {
        drupal_set_message('Bucket name not configured.'. 'error');
      }
      else {
        try {
         $this->s3 = new AmazonS3();
         $this->bucket = $bucket;
        }
        catch(RequestCore_Exception $e){
          drupal_set_message('There was a problem connecting to S3', 'error');
        }
        catch(Exception $e) {
          drupal_set_message('There was a problem using S3: ' . $e->getMessage(), 'error');
        }
      }
    }
    return $this->s3;
  }

  /**
   * Get file status
   *
   * @return
   *   An array with file status, or FALSE in case of an error - see fstat()
   *   for a description of this array.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-stat.php
   */
  protected function _stat($uri = NULL) {
    if(!isset($uri)) {
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
        $stat[4] = $stat['uid'] = $metadata['uid'];
        $stat[7] = $stat['size'] = $metadata['filesize'];
        $stat[8] = $stat['atime'] = $metadata['timestamp'];
        $stat[9] = $stat['mtime'] = $metadata['timestamp'];
        $stat[10] = $stat['ctime'] = $metadata['timestamp'];
      }
      return $stat;
    }
    return FALSE;
}


/**
 * Determine whether the $uri is a directory
 *
   * @param $uri
   *   A string containing the uri to the resource to check. If none is given
   *   defaults to $this->uri
   *
   * @return
   *   TRUE if the resource is a directory
   */
  protected function _is_dir($uri = null) {
    if($uri == null) {
      $uri = $this->uri;
    }
    if($uri != null) {
      $path = $this->getLocalPath($uri);
      $response = $this->getS3()->list_objects($this->bucket, array(
          'prefix' => $path . '/',
          'max-keys' => 1,
      ));
      if($response && isset($response->body->Contents->Key)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * CACHING FUNCTIONS
   */

  /**
   * Try to fetch an object from the metadata cache, otherwise fetch it's
   * info from S3 and populate the cache.
   *
   * @param uri
   *   A string containing the uri of the resource to check.
   * @param $cach
   *   A boolean representing whether to check the cache for file information.
   *
   *  @return
   *    An array if the $uri exists, otherwise FALSE.
   */
  protected function _amazons3_get_object($uri, $cache = TRUE) {
    $uri = rtrim($uri,'/');

    if ($cache) {
      $metadata = $this->_amazons3_get_cache($uri);
      if ($metadata) {
        return $metadata;
      }
    }

    $is_dir = $this->_is_dir($uri);
    $metadata = NULL;
    if ($is_dir) {
      $mode = 0040000; // S_IFDIR indicating directory
      $mode |= 0777;
      $metadata = array(
        'uri' => $uri,
        'dir' => 1,
        'mode' => $mode,
      );
    }
    else {
      $response = $this->getS3()->get_object_metadata($this->bucket, $this->getLocalPath($uri));
      if ($response) {
        $metadata = $this->_amazons3_format_response($uri, $response);
        $metadata['dir'] = 0;
        $metadata['mode'] = 0100000; // S_IFREG indicating file
        $metadata['mode'] |= 0777; // everything is writeable
      }
    }
    if (is_array($metadata)) {
      // Save to the cache
      db_merge('amazons3_file')
        ->key(array('uri' => $metadata['uri']))
        ->fields($metadata)
        ->execute();
      return $metadata;
    }
    return FALSE;
  }

  /**
   * Fetch an object from the local metadata cache
   *
   * @param uri
   *  A string containing the uri of the resource to check.
   *
   *  @return
   *    An array if the $uri is in the cache, otherwise FALSE
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
   * Format returned file information from S3 into an array
   *
   * @param $uri
   *   A string containing the uri of the resource to check.
   * @param $response
   *   An array containing the collective metadata for the Amazon S3 object
   *
   * @return
   *   An array containing formatted metadata
   */
  protected function _amazons3_format_response($uri, $response) {
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
    return $metadata;
  }
}
