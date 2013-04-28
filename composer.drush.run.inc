<?php
/**
 * @file
 * Provides the Composer integration in a seperate file so that PHP < 5.3 works.
 */

use Composer\Console\Application;

/**
 * Executes the given command in the current working directory.
 */
function drush_composer_run($arguments = array()) {
  // Prepend Composer to the list of arguments.
  array_unshift($arguments, 'composer');

  // Append all the command line options (--option and -o).
  foreach ($_SERVER['argv'] as $index => $arg) {
    if ($arg[0] == '-') {
      $arguments[] = $arg;
    }
  }

  // Backup and override the server command line arguments.
  $backup = $_SERVER['argv'];
  $_SERVER['argv'] = $arguments;
  $_SERVER['argc'] = count($arguments);

  // Autoload the required classes.
  $loader = require_once(__DIR__ . '/vendor/autoload.php');

  // Run the Composer command.
  $application = new Application();
  $application->setAutoExit(false);
  $application->run();

  // Now that Composer has run, restore the original server arguments.
  $_SERVER['argv'] = $backup;
  $_SERVER['argc'] = count($backup);

  return $loader;
}
