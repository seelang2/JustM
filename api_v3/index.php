<?php
/*****
  index.php - api point-of-entry

 **/

// short alias to path delimiter
define('DS', DIRECTORY_SEPARATOR);
// absolute FS path to API core directory location including trailing slash
define('API_CORE_PATH',DS.'www'.DS.'localhost'.DS.'public_html'.DS.'chrisrichron'.DS.'api_v3');
// name of the API core directory
define('API_CORE_DIR','core');
// API base URI location with leading and trailing slashes
define('URI_BASE',DS.'chrisrichron'.DS.'api_v3'.DS);

// load API core
require(API_CORE_PATH.DS.API_CORE_DIR.DS.'core.php');

