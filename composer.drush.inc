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
      'require-dev' => 'Package to require for development with a version constraint, e.g. foo/bar:1.0.0 or foo/bar=1.0.0 or "foo/bar 1.0.0".',
      'quiet' => 'Do not output any message.',
      'version' => 'Display Composer version information.',
      'ansi' => 'Force ANSI output.',
      'no-ansi' => 'Disable ANSI output.',
      'profile' => 'Display timing and memory usage information.',
      'no-interaction' => 'Do not ask any interactive questions.',
      'no-custom-installers' => 'Disables all custom installers.',
      'optimize' => 'Optimizes PSR0 packages to be loaded with classmaps too, good for production.',
      'verbose' => 'Increase verbosity of messages.',
      'prefer-source' => 'Forces isntallation from package sources when possible, including VCS information.',
      'prefer-dist' => 'Forces installation from package dist even for dev versions.',
      'dry-run' => 'Outputs the operations but will not execute anything.',
      'no-scripts' => 'Skips the execution of all scripts defined in the composer.json file.',
      'xml' => 'To output as XML',
      'help' => 'Display help message.',
      'working-dir' => 'If specified, use the given directory as working directory.',
      'global' => 'Apply command to the global config file.',
      'editor' => 'Open editor',
      'unset' => 'Unset the given setting-key.',
      'list' => 'List configuration settings.',
      'no-update' => 'Disables the automatic update of the dependencies.',
      'file' => 'If you want to choose a different composer.json or config.json.',
      'repository-url' => 'Pick a different repository url to look for the package.',
      'keep-vcs' => 'Whether to prevent deletion vcs folder.',
      'minimum-stability' => 'Minimum stability (empty or one of: stable, RC, beta, alpha, dev)',
      'name' => 'Name of the package',
      'description' => 'Description of package',
      'author' => 'Author name of package',
      'homepage' => 'Homepage of package',
      'only-name' => 'Search only in name',
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
