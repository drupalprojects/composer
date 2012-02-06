<?php
/**
 * @file
 * Drush wrapper around the Composer package management system.
 */

/**
 * Implementation of hook_drush_command().
 */
function composer_drush_command() {
  $items['composer'] = array(
    'description' => 'Perform package management and dependency tracking of your projects and libraries.',
    'arguments' => array(
      'command' => 'Run "drush composer" for a list of available commands.',
    ),
    'examples' => array(
      'drush composer' => 'Display available commands with composer.',
      'drush composer install' => 'Parses composer.json in the current directory.',
      'drush composer updates' => 'Updates dependencies to the latest version.',
      'drush composer show symfony/symfony' => 'Displays package information about Symfony.',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );
  return $items;
}

/**
 * Drush Composer; Invokes a Composer command.
 */
function drush_composer($command = '') {
  // Make sure Composer is installed and available. Cache it for a week.
  $path = drush_directory_cache();
  $composer = drush_download_file('http://getcomposer.org/composer.phar', $path . '/composer.phar', 604800);

  // Make sure Composer was loaded corectly.
  if (drush_file_not_empty($composer)) {
    // Composer uses argv to retrieve command line arguments, so we emulate the
    // arguments for Composer.
    $_SERVER['argv'] = array($composer);
    foreach (func_get_args() as $arg) {
      $_SERVER['argv'][] = $arg;
    }

    // Invoke Composer to process the command.
    drush_set_context('DRUSH_EXECUTION_COMPLETED', TRUE);
    include($composer);
  }
  else {
    // Display an error log about Composer not being loaded.
    drush_log(dt('Composer was not installed correctly.'), 'error');
  }
}
