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

// PHP only supports automatic handling of GET and POST requests
// other request data is accessible from standard input
// read request data from standard input into a variable
// ref: http://www.lornajane.net/posts/2008/accessing-incoming-put-data-from-php
//parse_str(file_get_contents("php://input"),$REQUEST);
$tmpData = file_get_contents("php://input");
if (DEBUG_MODE) Message::addDebugMessage('stdInput',$tmpData);
parse_str($tmpData,$REQUEST);

// database initialization
try {
    $db = new PDO('mysql:dbname='.DB_NAME.';host='.DB_HOST,
    			  DB_USER,
    			  DB_PASSWORD);
} catch (PDOException $e) {
    // not sure if it's proper to send a 5xx status response in this situation.
    // need more research.
	$message = 'Connection to database server failed';
    if (DEBUG_MODE) $message .= ': '.$e->getMessage();
    Message::stopError(500, $message);
}


if (DEBUG_MODE) Message::addDebugMessage('$_SERVER',$_SERVER);
if (DEBUG_MODE) Message::addDebugMessage('$_POST',$_POST);
if (DEBUG_MODE) Message::addDebugMessage('$_GET',$_GET);


// hand off the request to the Dispatcher
Dispatcher::route($db, $_SERVER['REQUEST_URI']);


