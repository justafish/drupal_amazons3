<?php

use Drupal\amazons3\Exception\S3ConnectValidationException;
use Drupal\amazons3\S3Client;

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
  );

  $form['amazons3_cache'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enable database caching'),
    '#description' => t('Enable a local file metadata cache, this significantly reduces calls to S3'),
    '#default_value' => variable_get('amazons3_cache', 1),
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
    '#description' => t('Deliver URLs through a CloudFront domain when using presigned URLs.'),
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

  $form['amazons3_https'] = array(
    '#type' => 'textarea',
    '#title' => t('Force HTTPS'),
    '#description' => t('A list of paths that should be delivered through a https url. This is not necessary if the current page being served is already HTTPS. Enter one value per line e.g. "mydir/*". Paths are relative to the Drupal file directory and use patterns as per <a href="@preg_match">preg_match</a>.', array('@preg_match' => 'http://php.net/preg_match')),
    '#default_value' => variable_get('amazons3_https', ''),
    '#rows' => 10,
  );

  $form['amazons3_torrents'] = array(
    '#type' => 'textarea',
    '#title' => t('Torrents'),
    '#description' => t('A list of paths that should be delivered through a torrent url. Enter one value per line e.g. "mydir/*". Paths are relative to the Drupal file directory and use patterns as per <a href="@preg_match">preg_match</a>. This won\'t work for CloudFront presigned URLs.', array('@preg_match' => 'http://php.net/preg_match')),
    '#default_value' => variable_get('amazons3_torrents', ''),
    '#rows' => 10,
  );

  $form['amazons3_presigned_urls'] = array(
    '#type' => 'textarea',
    '#title' => t('Presigned URLs'),
    '#description' => t('A list of timeouts and paths that should be delivered through a presigned url. Enter one value per line, in the format &lt;timeout&gt;|&lt;path&gt;|&lt;protocol&gt;. e.g. "60|mydir/*" or "60|mydir/*|https". Paths are relative to the Drupal file directory and use patterns as per <a href="@preg_match">preg_match</a>.', array('@preg_match' => 'http://php.net/preg_match')),
    '#default_value' => variable_get('amazons3_presigned_urls', ''),
    '#rows' => 10,
  );

  $form['amazons3_saveas'] = array(
    '#type' => 'textarea',
    '#title' => t('Force Save As'),
    '#description' => t('A list of paths that force the user to save the file by using Content-disposition header. Prevents autoplay of media. Enter one value per line. e.g. "mydir/*". Paths are relative to the Drupal file directory and use patterns as per <a href="@preg_match">preg_match</a>. Files must use a presigned url to use this, however it won\'t work for CloudFront presigned URLs and you\'ll need to set the content-disposition header in the file metadata before saving.', array('@preg_match' => 'http://php.net/preg_match')),
    '#default_value' => variable_get('amazons3_saveas', ''),
    '#rows' => 10,
  );

  $form['amazons3_rrs'] = array(
    '#type' => 'textarea',
    '#title' => t('Reduced Redundancy Storage'),
    '#description' => t('A list of paths that save the file in <a href="@rrs">Reduced Redundancy Storage</a>. Enter one value per line. e.g. "styles/*". Paths are relative to the Drupal file directory and use patterns as per <a href="@preg_match">preg_match</a>.', array('@rrs' => 'http://aws.amazon.com/s3/faqs/#rrs_anchor', '@preg_match' => 'http://php.net/preg_match')),
    '#default_value' => variable_get('amazons3_rrs', ''),
    '#rows' => 10,
  );

  $form['amazons3_clear_cache'] = array(
    '#type' => 'fieldset',
    '#title' => t('Clear cache'),
  );

  $form['amazons3_clear_cache']['clear'] = array(
    '#type' => 'submit',
    '#value' => t('Clear file metadata cache'),
    '#submit' => array('amazons3_clear_cache_submit'),
  );

  return system_settings_form($form);
}

/**
 * Validate the admin form.
 */
function amazons3_admin_validate($form, &$form_state) {
  $bucket = $form_state['values']['amazons3_bucket'];
  $cloudfront = $form_state['values']['amazons3_cloudfront'];

  if ($cloudfront) {
    $keypair = variable_get('aws_cloudfront_keypair', '');
    $pem = variable_get('aws_cloudfront_pem', '');
    if (empty($keypair) || empty($pem)) {
      form_set_error('amazons3_cloudfront', t('You must configure your CloudFront credentials in the awksdk module.'));
    }
  }

  $hostname = $form_state['values']['amazons3_hostname'];

  $config = array();
  if (!empty($hostname)) {
    $config['endpoint'] = $hostname;
  }

  try {
    $s3 = S3Client::factory($config);
    S3Client::validateBucketExists($bucket, $s3);
  }
  catch (S3ConnectValidationException $e) {
    form_set_error('amazons3_bucket', t('The S3 access credentials are invalid or the bucket does not exist.'));
  }
  catch (Exception $e) {
    form_set_error('amazons3_bucket', t('There was a problem connecting to S3. The following exception was thrown: @exception', array('@exception' => $e->getMessage())));
  }
}

/**
 * Admin sub-form submit callback; clear file metadata cache.
 */
function amazons3_clear_cache_submit($form, &$form_state) {
  db_query('TRUNCATE TABLE {amazons3_file}');
  drupal_set_message(t('Cache cleared.'));
}

