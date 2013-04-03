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

/**
 * Parses composer.drush.xml to get the available options.
 *
 * drush composer
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
