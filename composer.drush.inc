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
    'options' => array(
      'dev' => 'Specifies that the development requirements are to be retrieved.',
      'quiet' => 'Do not output any message.',
      'version' => 'Display Composer version information.',
      'ansi' => 'Force ANSI output.',
      'no-ansi' => 'Disable ANSI output.',
      'no-interaction' => 'Do not ask any interactive questions.',
      'verbose' => 'Increase verbosity of messages.',
      'prefer-source' => 'Forces isntallation from package sources when possible, including VCS information.',
      'dry-run' => 'Outputs the operations but will not execute anything.',
      'no-scripts' => 'Skips the execution of all scripts defined in the composer.json file.',
    ),
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
  // Autoload the required classes.
  $loader = require_once(__DIR__ . '/vendor/autoload.php');

  // Composer uses argv to retrieve command line arguments, so we will emulate
  // any options passed in.
  $options = array();
  foreach ($_SERVER['argv'] as $option) {
    if (substr($option, 0, 2) == '--') {
      $options[] = $option;
    }
  }

  // Now that we have the options set up, we'll mask the actual arguments.
  $_SERVER['argv'] = array('composer');
  foreach (func_get_args() as $arg) {
    $_SERVER['argv'][] = $arg;
  }
  foreach ($options as $option) {
    $_SERVER['argv'][] = $option;
  }
  $_SERVER['argc'] = count($_SERVER['argv']);

  // Tell Drush that it ran successfully.
  drush_set_context('DRUSH_EXECUTION_COMPLETED', TRUE);

  // Run the Composer command.
  $application = new Composer\Console\Application();
  $application->setAutoExit(false);
  $application->run();

  return $loader;
}

/**
 * Implementation of hook_drush_pm_post_download().
 */
function composer_drush_pm_post_download($project, $release) {
  // Check if there's a composer.json to process.
  $path = isset($project['full_project_path']) ? $project['full_project_path'] : FALSE;
  if ($path && file_exists($path . '/composer.json')) {
    // Composer uses the current working directory, so we'll mock it as the
    // current working directory.
    $original = getcwd();
    chdir($path);

    // Run the Composer installation.
    drush_composer('install');

    // Switch back to the original file path.
    chdir($original);
  }
}
