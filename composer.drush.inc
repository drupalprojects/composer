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
 *
 * @param $command
 *   The Composer command we are to execute.
 */
function drush_composer($command = '') {
  // Autoload the required classes.
  $loader = require_once(__DIR__ . '/vendor/autoload.php');

  // Composer uses argv to retrieve command line arguments, so we emulate the
  // arguments for Composer.
  $_SERVER['argv'] = array($composer);
  foreach (func_get_args() as $arg) {
    $_SERVER['argv'][] = $arg;
  }

  // Tell Drush we ran successfully.
  drush_set_context('DRUSH_EXECUTION_COMPLETED', TRUE);

  // Run the Composer command.
  $application = new Composer\Console\Application();
  $application->run();

  return $loader;
}
