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
 * Allows modules to add arguments to an S3 command. Each S3 command corresponds
 * to an S3 API call, such as 'GetObject'. The 's3-2006-03-01.php' file shipped
 * with the AWS SDK has a complete listing of all supported commands and
 * parameters under the 'operations' key in the array.
 *
 * @param string $name
 *   The name of the command being prepared, such as 'HeadObject'.
 * @param array $args
 *   An array of parameters used to create the command.
 *
 * @return array $args
 *   Any additional arguments to add to the command.
 */
function hook_amazons3_command_prepare($name, array $args) {
  if ($name == 'GetObject') {
    $args['ResponseCacheControl'] = 'no-cache';
  }

  return $args;
}

/**
 * Allows modules to alter the arguments to an S3 command.
 *
 * @see hook_amazons3_command_prepare()
 *
 * @param string &$name
 *   The name of the command being prepared, such as 'HeadObject'.
 * @param &$args
 *   An array of parameters used to create the command.
 */
function hook_amazons3_command_prepare_alter(&$name, &$args) {
  if ($args['Bucket'] == 'bucket.example.com') {
    $args['ResponseCacheControl'] = 'no-cache';
  }
}

/**
 * Allows modules to alter an S3 command after it has been created.
 *
 * @param \Guzzle\Service\Command\CommandInterface $command
 *   The command that was created.
 */
function hook_amazons3_command_alter(\Guzzle\Service\Command\CommandInterface $command) {
  if ($command->getName('HeadObject')) {
    $command->setOnComplete(function() {
      watchdog('amazons3', 'HeadObject was called.');
    });
  }
}
