<?php
/*****
  config.php - basic configuration settings
 **/

// set a quick debugging mode to control verbose output
// will cause output to be placed in an envelope
define('DEBUG_MODE', true);

define('HEADER_PREFIX', 'X-API-');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'timeslips');
define('DB_USER', 'dbuser');
define('DB_PASSWORD', 'password');

// flag whether an envelope should be used for the JSON output
// setting DEBUG_MODE to true overrides this to true
$useJsonEnvelope = empty($_GET['envelope']) || $_GET['envelope'] == 'false' ? false : true;
if (DEBUG_MODE) $useJsonEnvelope = true;

