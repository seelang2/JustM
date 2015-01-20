<?php
/*********
 * @name: core.php - app bootstrap code
 * @author: Chris Langtiw
 *
 * @description: 
 * 
 ****/

require(API_CORE_PATH.'config.php');
require(API_CORE_PATH.'core_lib.php');

// set output headers
//if (!DEBUG_MODE) header('Content Type: application/json');

// prepare output renderer
$output = new Message();

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


if (DEBUG_MODE) $output->addDebugMessage('$_SERVER',$_SERVER);
if (DEBUG_MODE) $output->addDebugMessage('$_POST',$_POST);
if (DEBUG_MODE) $output->addDebugMessage('$_GET',$_GET);


// hand the parameter array to the Dispatcher
//$params = Dispatcher::parseRequest($_SERVER['REQUEST_URI']);
//if (DEBUG_MODE) $output->addDebugMessage('request_params', $params);

Dispatcher::route();

// just to test sending HTTP response code
//header("HTTP/1.1 500 Internal Server Error");

$output->render($useJsonEnvelope);


