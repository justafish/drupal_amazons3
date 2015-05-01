<?php

/**
 * Form API callback for the configuration form.
 */
function amazons3_admin() {
  $form = array();

  $form['amazons3_key'] = array(
    '#type' => 'textfield',
    '#title' => t('Amazon S3 API Key'),
    '#default_value' => variable_get('amazons3_key', ''),
    '#required' => TRUE,
  );

  $form['amazons3_secret'] = array(
    '#type' => 'textfield',
    '#title' => t('Amazon S3 API Secret'),
    '#default_value' => variable_get('amazons3_secret', ''),
    '#required' => TRUE,
  );

  $form['amazons3_bucket'] = array(
    '#type' => 'textfield',
    '#title' => t('Default Bucket Name'),
    '#default_value' => variable_get('amazons3_bucket', ''),
    '#required' => TRUE,
    '#element_validate' => array('amazons3_form_bucket_validate'),
  );

  $form['amazons3_cache'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enable metadata caching'),
    '#description' => t('Enable a local file metadata cache to reduce calls to S3.'),
    '#default_value' => variable_get('amazons3_cache', TRUE),
  );

  $expiration = variable_get('amazons3_cache_expiration', CACHE_PERMANENT);

  $period = drupal_map_assoc(array(0, 60, 180, 300, 600, 900, 1800, 2700, 3600, 10800, 21600, 32400, 43200, 86400), 'format_interval');
  $period[0] = '<' . t('none') . '>';
  $form['amazons3_cache_expiration'] = array(
    '#type' => 'select',
    '#title' => t('Expiration of cached file metadata'),
    '#default_value' => $expiration,
    '#options' => $period,
    '#description' => t('The maximum time Amazon S3 file metadata will be cached. If multiple API clients are interacting with the same S3 buckets, this setting might need to be reduced or disabled.'),
    '#states' => array(
      'visible' => array(
        ':input[name="amazons3_cache"]' => array('checked' => TRUE),
      ),
    ),
  );

  $form['amazons3_cname'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enable CNAME'),
    '#description' => t('Serve files from a custom domain by using an appropriately named bucket e.g. "mybucket.mydomain.com"'),
    '#default_value' => variable_get('amazons3_cname', 0),
  );

  $form['amazons3_domain'] = array(
    '#type' => 'textfield',
    '#title' => t('CDN Domain Name'),
    '#description' => t('If serving files from CloudFront then the bucket name can differ from the domain name.'),
    '#default_value' => variable_get('amazons3_domain', ''),
    '#states' => array(
      'visible' => array(
        ':input[id=edit-amazons3-cname]' => array('checked' => TRUE),
      ),
    ),
  );

  $form['amazons3_cloudfront'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enable CloudFront'),
    '#description' => t('Deliver URLs through a CloudFront domain when using presigned URLs. This requires additional settings.php configuation. See README.md for details. Note that CloudFront URLs do not support other configuration options like Force Save As or Torrents, but they do support presigned URLs.'),
    '#default_value' => variable_get('amazons3_cloudfront', 0),
    '#states' => array(
      'visible' => array(
        ':input[id=edit-amazons3-cname]' => array('checked' => TRUE),
      ),
    ),
  );

  $form['amazons3_hostname'] = array(
    '#type' => 'textfield',
    '#title' => t('Custom Hostname'),
    '#description' => t('For use with an alternative API compatible service e.g. <a href="@cloud">Google Cloud Storage</a>', array('@cloud' => 'https://cloud.google.com/storage‎')),
    '#default_value' => variable_get('amazons3_hostname', ''),
  );

  $form['amazons3_torrents'] = array(
    '#type' => 'textarea',
    '#title' => t('Torrents'),
    '#description' => t('A list of paths that should be delivered through a torrent url. Enter one value per line e.g. "mydir/.*". Paths are relative to the Drupal file directory and use patterns as per <a href="@preg_match">preg_match</a>. This won\'t work for CloudFront presigned URLs.', array('@preg_match' => 'http://php.net/preg_match')),
    '#default_value' => _amazons3_implode('amazons3_torrents'),
    '#rows' => 10,
  );

  $form['amazons3_presigned_urls'] = array(
    '#type' => 'textarea',
    '#title' => t('Presigned URLs'),
    '#description' => t('A list of timeouts and paths that should be delivered through a presigned url. Enter one value per line, in the format &lt;timeout&gt;|&lt;path&gt; e.g. "60|mydir/.*". Paths are relative to the Drupal file directory and use patterns as per <a href="@preg_match">preg_match</a>.', array('@preg_match' => 'http://php.net/preg_match')),
    '#default_value' => _amazons3_implode('amazons3_presigned_urls'),
    '#rows' => 10,
  );

  $form['amazons3_saveas'] = array(
    '#type' => 'textarea',
    '#title' => t('Force Save As'),
    '#description' => t('A list of paths that force the user to save the file by using Content-disposition header. Prevents autoplay of media. Enter one value per line. e.g. "mydir/.*". Paths are relative to the Drupal file directory and use patterns as per <a href="@preg_match">preg_match</a>. Files must use a presigned url to use this, however it won\'t work for CloudFront presigned URLs and you\'ll need to set the content-disposition header in the file metadata before saving.', array('@preg_match' => 'http://php.net/preg_match')),
    '#default_value' => _amazons3_implode('amazons3_saveas'),
    '#rows' => 10,
  );

  $form['amazons3_rrs'] = array(
    '#type' => 'textarea',
    '#title' => t('Reduced Redundancy Storage'),
    '#description' => t('A list of paths that save the file in <a href="@rrs">Reduced Redundancy Storage</a>. Enter one value per line. e.g. "styles/.*". Paths are relative to the Drupal file directory and use patterns as per <a href="@preg_match">preg_match</a>.', array('@rrs' => 'http://aws.amazon.com/s3/faqs/#rrs_anchor', '@preg_match' => 'http://php.net/preg_match')),
    '#default_value' => _amazons3_implode('amazons3_rrs'),
    '#rows' => 10,
  );

  $form = system_settings_form($form);
  $form['#submit'] = array_merge(array('_amazons3_explode_list'), $form['#submit']);
  return $form;
}

/**
 * Validate the admin form.
 */
function amazons3_admin_validate($form, &$form_state) {
  $bucket = $form_state['values']['amazons3_bucket'];
  $cloudfront = $form_state['values']['amazons3_cloudfront'];

  if ($cloudfront) {
    $keypair = variable_get('amazons3_cloudfront_keypair_id', FALSE);
    $pem = variable_get('amazons3_cloudfront_private_key', FALSE);
    if (empty($keypair) || empty($pem)) {
      form_set_error('amazons3_cloudfront', t('You must configure your CloudFront credentials in settings.php.'));
    }
  }
}

/**
 * Explode newlines in our admin form into arrays to save.
 *
 * @param array $form
 *   The form being displayed.
 * @param array &$form_state
 *   The current state of the form.
 */
function _amazons3_explode_list($form, &$form_state) {
  $keys = array(
    'amazons3_presigned_urls',
    'amazons3_rrs',
    'amazons3_saveas',
    'amazons3_torrents',
  );

  $values = &$form_state['values'];

  foreach ($keys as $form_key) {
    $values[$form_key] = explode("\n", $values[$form_key]);
    $values[$form_key] = array_map('trim', $values[$form_key]);
    $values[$form_key] = array_filter($values[$form_key], 'strlen');
  }

  // Presigned URLs are special in that they are pipe-separated lines.
  $presigned_config = array();
  foreach ($values['amazons3_presigned_urls'] as $presigned_line) {
    list($timeout, $pattern) = explode("|", $presigned_line);
    $presigned_config[] = array(
      'timeout' => $timeout,
      'pattern' => $pattern,
    );
  }
  $values['amazons3_presigned_urls'] = $presigned_config;
}

/**
 * Load a variable array and implode it into a string.
 *
 * @param string $name
 *   The variable to load.
 *
 * @return string
 *   The imploded string.
 */
function _amazons3_implode($name) {
  if ($name == 'amazons3_presigned_urls') {
    $lines = array();
    foreach (variable_get($name, array()) as $config) {
      $lines[] = $config['timeout'] . '|' . $config['pattern'];
    }
    return implode("\n", $lines);
  }

  return implode("\n", variable_get($name, array()));
}
