<?php

/**
 * @file media_amazon_s3_streams.inc
 * Implement DrupalStreamWrapperInterface to provide an s3://
 * streamwrapper.
 */
class MediaAmazonS3StreamWrapper implements DrupalStreamWrapperInterface {

  /**
   * Instance URI (stream).
   *
   * A stream is referenced as "s3://bucket/key".
   *
   * @var String
   */
  protected $uri;
  
  /**
   * File pointer
   *
   * @var int
   */
  protected $position;
  
  /**
   * S3 connection object
   *
   * @var AmazonS3
   */
  protected $s3 = null;
  
  /**
   * S3 bucket name
   *
   * @var string
   */
  protected $bucket;
  
  /**
   * Object cache
   *
   * @var CFResponse
   */
  protected $object = null;
  
  protected $object_exists = null;

  public function setUri($uri) {
    $this->uri = $uri;
  }
   
  public function getUri() {
     return $this->uri;
   }

  public function getExternalUrl() {
    if(!isset($this->bucket)) {
      $this->bucket = $bucket = variable_get('media_amazon_s3_bucket', '');
    }
    $url = 'http://' . $this->bucket . '.s3.amazonaws.com/' . $this->getLocalPath();
    return $url;
  }

  public static function getMimeType($uri, $mapping = NULL) {
     if (!isset($mapping)) {
       // The default file map, defined in file.mimetypes.inc is quite big.
       // We only load it when necessary.
       include_once DRUPAL_ROOT . '/includes/file.mimetypes.inc';
       $mapping = file_mimetype_mapping();
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
       if (isset($mapping['extensions'][$extension])) {
         return $mapping['mimetypes'][$mapping['extensions'][$extension]];
       }
     }

     return 'application/octet-stream';
   }

  public function chmod($mode) {
     return TRUE;
   }

  public function realpath() {
     return 's3://' . $this->getLocalPath();
   }

  public function dirname($uri = NULL) {
   list($scheme, $target) = explode('://', $uri, 2);
   $target  = $this->getTarget($uri);
   $dirname = dirname($target);

   if ($dirname == '.') {
     $dirname = '';
   }

   return $scheme . '://' . $dirname;
  }

  public function stream_open($uri, $mode, $options, &$opened_path) {
    $this->uri = $uri;
    if($this->getS3()) {
      $this->position = 0;
      return TRUE;
    }
  }

  public function stream_close() {
    $this->pointer = 0;
    $this->flushObject();
    return TRUE;
  }

  public function stream_lock($operation) {
    return lock_aquire('media_amazon_s3:' . $this->uri);
  }

  public function stream_read($count) {
    if($this->getS3()) {
      if($this->objectExists($this->uri)) {
        $object = substr($this->getObject($this->uri)->body, $this->position, $count);
        $this->position += $count;
        return $object;
      }
    }
    return FALSE;
  }

  public function stream_write($data) {
    if($this->getS3()) {
      $left = '';
      $right = '';
      
      if($this->objectExists($this->uri)) {
        $object = $this->getObject($this->uri)->body;
        $left = substr($object, 0, $this->position);
        $right = substr($object, $this->position + strlen($data));
      }
      $response = $this->getS3()->create_object($this->bucket, $this->getLocalPath(), array(
        'body' => $left . $data . $right,
        'acl' => AmazonS3::ACL_PUBLIC,
        'contentType' => MediaAmazonS3StreamWrapper::getMimeType($this->uri),
      ));
      if($response->isOK()) {
       $this->position += strlen($data);
       
       // update object cache
       $object_cache = $this->getObject($this->uri);
       $object_cache->body = $left . $data . $right;
       $object_cache->acl = AmazonS3::ACL_PUBLIC;
       $this->setObject($object_cache);
       
       return strlen($data); 
      }
    }
    return FALSE;
  }

  public function stream_eof() {
    if($this->s3 && $this->s3->if_object_exists($this->bucket, $this->getLocalPath())) {
      $object = $this->s3->get_object($this->bucket, $this->getLocalPath())->body;
      return $this->position >= strlen($object); 
    }
    return TRUE;
  }

  public function stream_seek($offset, $whence) {
    switch($whence) {
      case SEEK_SET:
        if($this->getS3() && $this->$this->getS3()->if_object_exists($this->bucket, $this->getLocalPath())) {
          $object = $this->getS3()->get_object($this->bucket, $this->getLocalPath())->body;
          if ($offset < strlen($object) && $offset >= 0) {
            $this->position = $offset;
            return TRUE;
          }
        }
        return FALSE;
      case SEEK_CUR:
        if ($offset >= 0) {
          $this->position += $offset;    
          return TRUE;
        }
        return FALSE;
      case SEEK_END:
        if($this->getS3() && $this->getS3()->if_object_exists($this->bucket, $this->getLocalPath())) {
          $object = $this->getS3()->get_object($this->bucket, $this->getLocalPath())->body;
          if (strlen($object) + $offset >= 0) {
            $this->position = strlen($object) + $offset;
            return TRUE;
          }
        }
        return FALSE;
      default:
        return FALSE;
    }
    return FALSE;
  }

  public function stream_flush() {
    return TRUE;
    $this->flushObject();
  }

  public function stream_tell() {
    return $this->position;
  }

  public function stream_stat() {
    return $this->_stat();
  }

  public function unlink($uri) {
    if($this->getS3()) {
      $response = $this->getS3()->delete_object($this->bucket, $this->getLocalPath($uri));
      if($response->isOK()) {
        $this->object = null;
        $this->object_exists = null;
      }
      return $response->isOK();
    }
    return false;    
  }
 
  public function rename($from_uri, $to_uri) {
    return false;
  }

  protected function getTarget($uri = NULL) {
    if (!isset($uri)) {
      $uri = $this->uri;
    }

    list($scheme, $target) = explode('://', $uri, 2);

    // Remove erroneous leading or trailing, forward-slashes and backslashes.
    // In the session:// scheme, there is never a leading slash on the target.
    return trim($target, '\/');
  }

  public function mkdir($uri, $mode, $options) {
    
    $recursive = (bool) ($options & STREAM_MKDIR_RECURSIVE);
    $localpath = $this->getLocalPath($uri);
    
    // s3 has no concept of directories, but we emulate it by creating an object of size 0 with a trailing "/"
    if($this->getS3()) {
      $response = $this->getS3()->create_object($this->bucket, $localpath . '/', array('body' => ''));
      if($response->isOk()) {
        return true;
      }
    }
    return false;
  }

  public function rmdir($uri, $options) {
    // s3 has no concept of directories
    return false;
  }

  public function url_stat($uri, $flags) {
    return $this->_stat($uri);
  }

  public function dir_opendir($uri, $options) {
    return TRUE;
  }

  public function dir_readdir() {
    return FALSE;
  }

  public function dir_rewinddir() {
    return true;
  }

  public function dir_closedir() {
    return true;
  }
    
  protected function getLocalPath($uri = NULL) {
    if (!isset($uri)) {
      $uri = $this->uri;
    }

    $path  = str_replace('s3://', '', $uri);
    $path = trim($path, '/');
    return $path;
  }

  public function getDirectoryPath() {
    if(!isset($this->bucket)) {
      $this->bucket = $bucket = variable_get('media_amazon_s3_bucket', '');
    }
    $url = 'http://' . $this->bucket . '.s3.amazonaws.com';
    return $url;
  }
  
  protected function setObject($object) {
    $this->object = $object;
    $this->object_exists = true;
  }
  
  protected function getObject($uri) {
    if($this->getS3() && $this->objectExists($uri)) {
      if(!$this->object) {
        $this->object = $this->getS3()->get_object($this->bucket, $this->getLocalPath($uri));
      }
      return $this->object;
    }
    return false;
  }

  protected function flushObject() {
    $this->object = null;
    $this->object_exists = null;
  }
  
  protected function objectExists($uri) {
    if($this->object_exists == null && $this->getS3()) {
      $this->object_exists = $this->getS3()->if_object_exists($this->bucket, $this->getLocalPath($uri));
    }
    return $this->object_exists;
  }

  protected function getS3() {
    if($this->s3 == null) {
      $key = variable_get('media_amazon_key', '');
      $secret_key = variable_get('media_amazon_key_secret', '');
      $bucket = variable_get('media_amazon_s3_bucket', '');
    
      if(!libraries_load('awssdk') && !isset($key) && !isset($secret_key) && !isset($bucket)) {
        drupal_set_message('Unable to load the AWS SDK. Please check you have installed the library correctly and configured your S3 credentials.'. 'error');
      }
      else {
        try {
         $this->s3 = new AmazonS3($key, $secret_key);
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
  
  public function _stat($uri = null) {
    
    if($uri == null) {
      $uri = $this->uri;
    }
    $path = $this->getLocalPath($uri);
    if($this->getS3() && ($this->getS3()->if_object_exists($this->bucket, $path) || $this->_is_dir($uri))) {
      
      $mode = 0100000; // S_IFREG indicating file
      $mode |= 0777; // everything is writeable
      $stat = array(
          '0' => 0,
          '1' => 0,
          '2' => $mode,
          '3' => 0,
          '4' => 0,
          '5' => 0,
          '6' => 0,
          '7' => 0,
          '8' => 0,
          '9' => 0,
          '10' => 0,
          '11' => 0,
          '12' => 0,
          'dev' => 0,
          'ino' => 0,
          'mode' => $mode,
          'nlink' => 0,
          'uid' => 0,
          'gid' => 0,
          'rdev' => 0,
          'size' => 0,
          'atime' => 0,
          'mtime' => 0,
          'ctime' => 0,
          'blksize' => 0,
          'blocks' => 0,
      );
      
      // check if directory
      if($this->_is_dir($uri)) {
        $stat['mode'] = 0040000; // S_IFDIR indicating directory
        $stat['mode'] |= 0777;
        $stat['2'] =  $stat['mode'];
        return $stat;
      }
      else {
        $response = $this->getS3()->get_object_metadata($this->bucket, $this->getLocalPath($uri));
        if($response) {
          if(isset($response['Size'])) {
            $stat['size'] = $response['Size'];
          }
          if(isset($response['LastModified'])) {
            $stat['mtime'] = $response['LastModified'];
          }
          return $stat;
        }
      }
    }

    return false;
  }
  
  public function _is_dir($uri = null) {
    if($uri == null) {
      $uri = $this->uri;
    }
    $path = $this->getLocalPath($uri);
    if($this->getS3()) {
      // check if directory
      $response = $this->getS3()->list_objects($this->bucket, array(
          'prefix' => $path . '/',
          'max-keys' => 1,
      ));
      if($response && isset($response->body->Contents->Key)) {
        return true;
      }
    }
    return false;
  }
  
}