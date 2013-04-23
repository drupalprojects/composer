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
    'options' => drush_composer_getoptions(),
    'examples' => array(
      'drush composer' => 'Display available commands available with Composer.',
      'drush composer install' => 'Parses composer.json in the current directory.',
      'drush composer update' => 'Updates dependencies to the latest version.',
      'drush composer show symfony/symfony' => 'Displays package information about Symfony.',
      'drush composer create-project twig/twig' => 'Downloads and installs the Twig package.',
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
  // Make sure our PHP requirements are met.
  $php = '5.3.2';
  $current = phpversion();
  if (version_compare($current, $php, '<')) {
    drush_set_error('DRUSH_APPLICATION_ERROR', dt('Composer requires at least PHP !php in order to run properly. You are currently on PHP !current.', array(
      '!php' => $php,
      '!current' => $current,
    )));

    return false;
  }

  // Include the run in a different file so that PHP versions < 5.3 do not fail
  // parsing the file.
  require_once(dirname(__FILE__) . '/composer.drush.run.inc');

  return drush_composer_run($command);
}

/**
 * Parses composer.drush.xml to get the available options.
 */
function drush_composer_getoptions() {
  static $options = array();

  if (empty($options)) {
    // Inject additional information from the list XML file.
    // Use __FILE__ instead of __DIR__ as PHP 5.3 might not be available.
    $xml = @simplexml_load_file(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'composer.drush.xml');

    if ($xml) {
      foreach ($xml->commands->command as $command) {
        if (isset($command->options->option)) {
          foreach ($command->options->option as $option) {
            $name = str_replace('--', '', (string) $option['name']);
            $description = (string) $option->description;
            $options[$name] = $description;
          }
        }
      }
    }
  }

  return $options;
}
