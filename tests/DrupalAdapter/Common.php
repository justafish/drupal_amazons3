<?php

namespace Drupal\amazons3Test\DrupalAdapter;

/**
 * Methods that map to includes/common.inc.
 *
 * @trait Common
 * @package Drupal\amazons3\DrupalAdapter
 * @codeCoverageIgnore
 */
trait Common {

  /**
   * @param $path
   * @param $options
   * @return string
   */
  public static function url($path = NULL, $options = array()) {
    // Merge in defaults.
    $options += array(
      'fragment' => '',
      'query' => array(),
      'absolute' => FALSE,
      'alias' => FALSE,
      'prefix' => ''
    );

    // A duplicate of the code from url_is_external() to avoid needing another
    // function call, since performance inside url() is critical.
    if (!isset($options['external'])) {
      // Return an external link if $path contains an allowed absolute URL. Avoid
      // calling drupal_strip_dangerous_protocols() if there is any slash (/),
      // hash (#) or question_mark (?) before the colon (:) occurrence - if any -
      // as this would clearly mean it is not a URL. If the path starts with 2
      // slashes then it is always considered an external URL without an explicit
      // protocol part.
      $colonpos = strpos($path, ':');
      $options['external'] = (strpos($path, '//') === 0)
        || ($colonpos !== FALSE
          && !preg_match('![/?#]!', substr($path, 0, $colonpos))
          && drupal_strip_dangerous_protocols($path) == $path);
    }

    // Preserve the original path before altering or aliasing.
    $original_path = $path;

    // Allow other modules to alter the outbound URL and options.
    // drupal_alter('url_outbound', $path, $options, $original_path);

    if (isset($options['fragment']) && $options['fragment'] !== '') {
      $options['fragment'] = '#' . $options['fragment'];
    }

    if ($options['external']) {
      // Split off the fragment.
      if (strpos($path, '#') !== FALSE) {
        list($path, $old_fragment) = explode('#', $path, 2);
        // If $options contains no fragment, take it over from the path.
        if (isset($old_fragment) && !$options['fragment']) {
          $options['fragment'] = '#' . $old_fragment;
        }
      }
      // Append the query.
      if ($options['query']) {
        $path .= (strpos($path, '?') !== FALSE ? '&' : '?') . drupal_http_build_query($options['query']);
      }
      if (isset($options['https']) && variable_get('https', FALSE)) {
        if ($options['https'] === TRUE) {
          $path = str_replace('http://', 'https://', $path);
        }
        elseif ($options['https'] === FALSE) {
          $path = str_replace('https://', 'http://', $path);
        }
      }
      // Reassemble.
      return $path . $options['fragment'];
    }

    // Strip leading slashes from internal paths to prevent them becoming external
    // URLs without protocol. /example.com should not be turned into
    // //example.com.
    $path = ltrim($path, '/');

    global $base_url, $base_secure_url, $base_insecure_url;

    // The base_url might be rewritten from the language rewrite in domain mode.
    if (!isset($options['base_url'])) {
      $options['base_url'] = 'http://amazons3.example.com';
    }

    // The special path '<front>' links to the default front page.
    if ($path == '<front>') {
      $path = '';
    }

    $base = $options['absolute'] ? $options['base_url'] . '/' : base_path();
    $prefix = empty($path) ? rtrim($options['prefix'], '/') : $options['prefix'];

    $path = static::drupal_encode_path($prefix . $path);
    if ($options['query']) {
      return $base . $path . '?' . drupal_http_build_query($options['query']) . $options['fragment'];
    }
    else {
      return $base . $path . $options['fragment'];
    }
  }

  /**
   * @param $path
   * @return mixed
   */
  public static function drupal_encode_path($path) {
    return str_replace('%2F', '/', rawurlencode($path));
  }
}
