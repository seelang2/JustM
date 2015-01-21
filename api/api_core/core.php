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

// flag whether an envelope should be used for the JSON output
// setting DEBUG_MODE to true overrides this to true
Message::$useEnvelope = empty($_GET['envelope']) || $_GET['envelope'] == 'false' ? false : true;
if (DEBUG_MODE) Message::$useEnvelope = true;

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
	Message::addMessage('error_message','Connection to database server failed');
	Message::render();
	exit();
}


if (DEBUG_MODE) Message::addDebugMessage('$_SERVER',$_SERVER);
if (DEBUG_MODE) Message::addDebugMessage('$_POST',$_POST);
if (DEBUG_MODE) Message::addDebugMessage('$_GET',$_GET);


// hand off the request to the Dispatcher
Dispatcher::route($db, $_SERVER['REQUEST_URI']);


