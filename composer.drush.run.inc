<?php
/**
 * @file
 * Provides the Composer integration in a seperate file so that PHP < 5.3 works.
 */

/**
 * Executes the given command in the current working directory.
 */
function drush_composer_run($command = '') {
  // Autoload the required classes.
  $loader = require_once(__DIR__ . '/vendor/autoload.php');

  // Composer uses argv to retrieve command line arguments, so we will emulate
  // any options being passed in.
  $options = array();
  foreach ($_SERVER['argv'] as $option) {
    if (substr($option, 0, 2) == '--') {
      $options[] = $option;
    }
  }

  // Now that we have all the options, make sure to strip any invalid options
  // so that Composer still runs correctly.
  $valid_options = composer_drush_command();
  $valid_options = array_keys($valid_options['composer']['options']);
  foreach ($options as $key => $option) {
    // Go through each option and make sure it's valid (prefix with --).
    $is_valid = FALSE;
    foreach ($valid_options as $valid) {
      if ($option == '--' . $valid) {
        $is_valid = TRUE;
        break;
      }
    }
    // Remove the option if it is not valid.
    if (!$is_valid) {
      unset($options[$key]);
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

  // Run the Composer command.
  $application = new Composer\Console\Application();
  $application->setAutoExit(false);
  $application->run();

  return $loader;
}
