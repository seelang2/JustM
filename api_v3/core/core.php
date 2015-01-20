<?php
/*****
  core.php - core API initialization

 **/


require('config.php');
require('core_classes.php');

// set output headers
//if (!DEBUG_MODE) header('Content Type: application/json');

// prepare output renderer
$output = new Message();

// register class autoloader
spl_autoload_register(function ($class) {
//    global $output;
//    if (file_exists(API_CORE_PATH.DS.'models'.DS.$class.'.php')) {
	    include (API_CORE_PATH.DS.'models'.DS.$class.'.php');
//	} else {
//		header("HTTP/1.1 400 Bad Request");
//		$output->addMessage('error_message','Requested resource model does not exist: '.API_CORE_PATH.DS.'models'.DS.$class.'.php');
//		$output->render($useJsonEnvelope);
//		exit();
//	}
});

// database initialization
try {
    $db = new PDO('mysql:dbname='.DB_NAME.';host='.DB_HOST,
    			  DB_USER,
    			  DB_PASSWORD);
} catch (PDOException $e) {
    // not sure if it's proper to send a 5xx status response in this situation.
    // need more research.

    //echo 'Connection failed: ' . $e->getMessage();
	//exit('{"status":0,"message":"Connection to database server failed"}');
	header("HTTP/1.1 500 Internal Server Error");
	$output->addMessage('error_message','Connection to database server failed');
	$output->render($useJsonEnvelope);
	exit();
}


//echo $_SERVER['REQUEST_URI'];
//if (DEBUG_MODE) echo '<h3>$_SERVER:</h3><pre>'.print_r($_SERVER, true).'</pre>'; // dump server var for inspection 
//if (DEBUG_MODE) echo '<h3>$_POST:</h3><pre>'.print_r($_POST, true).'</pre>'; // dump server var for inspection 
//if (DEBUG_MODE) echo '<h3>$_GET:</h3><pre>'.print_r($_GET, true).'</pre>'; // dump server var for inspection 
if (DEBUG_MODE) $output->addDebugMessage('$_SERVER',$_SERVER);
if (DEBUG_MODE) $output->addDebugMessage('$_POST',$_POST);
if (DEBUG_MODE) $output->addDebugMessage('$_GET',$_GET);


// hand the parameter array to the Dispatcher
$params = Dispatcher::parseRequest($_SERVER['REQUEST_URI']);
if (DEBUG_MODE) $output->addDebugMessage('request_params', $params);

Dispatcher::route($params);

// just to test sending HTTP response code
//header("HTTP/1.1 500 Internal Server Error");

$output->render($useJsonEnvelope);


