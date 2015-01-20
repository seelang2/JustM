<?php
/****
 API iteration 1

 In this simplified version we demonstrate the basics of an api implementation 
 based off a common MVC pattern that uses a 'RESTish' syntax to perform actions.
 
 The structure for each request is the following:
	/api/model/action/id/more_params

 where model is the name of the database table, action is an action in the routing 
 system and can be view, save, and delete.

 Although this is modeled after MVC, since this is an api the View will always be 
 simply a data structure. Here we're simply manually output JSON encoding. More
 robust applications could include JSON-P and XML as options.

 Also, the API is data-centric, therefore no Controller component is really needed
 here. So this is more of a MV implementation that is fairly simple.

 This example doesn't really use any kind of real RESTful implementation and violates 
 some basic principles of a REST implementation, and is highly insecure, but it
 serves as a basic demonstration of the database call code. Future iterations of the 
 interface will delve more into proper REST implementation.

**/

// set a quick debugging mode to control verbose output
define('DEBUG_MODE', true);

// set output headers
if (!DEBUG_MODE) header('Content Type: application/json');

try {
    $db = new PDO('mysql:dbname=timeslips;host=localhost',
    			  'dbuser',
    			  'password');
} catch (PDOException $e) {
    //echo 'Connection failed: ' . $e->getMessage();
	exit('{"status":0,"message":"Connection to database server failed"}');

}

// data definitions. This is just a nested array with the fields for each table.
// used in this example for the view data
$modelFields = array(
	'projects' => array('id','user_id','name','description'),
	'roles' => array('id','rate','name','description','color'),
	'users' => array('id','firstname','lastname','email','password','authtoken','status'),
	'timeslips' => array('id','project_id','role_id','time_start','time_stop','duration')
);

//echo $_SERVER['REQUEST_URI'];
if (DEBUG_MODE) echo '<pre>'.print_r($_SERVER, true).'</pre>'; // dump server var for inspection 

$params = explode('/', $_SERVER['REQUEST_URI']);

if (DEBUG_MODE) echo '<pre>'.print_r($params,true).'</pre>'; // dunp $params for inspection

while (array_shift($params) != 'api_v1') {
	// do nothing, really. We're just getting rid of the part of the URI before 
	// the actual api params
	// this wouldn't be necessary if the api was located somewhere like
	// http://api.domain/com.
}

if (DEBUG_MODE) echo '<pre>'.print_r($params,true).'</pre>'; // view modified params array

// extract params from request array
$model = strtolower(array_shift($params)); // extract model
$action = strtolower(array_shift($params));
$id = strtolower(array_shift($params));
// $params now contains any additional parameters left over

/*
	The routing component is a simple switch to control actions performed on a 
	model. More complex MVC applications would implement controller classes and 
	instantiate them, then call the appropriate controller method for the action.
*/
// pass params to routing system
switch( $action ) {
	case 'view':
	// get list of records or single record if id is present

		$query = 'SELECT * FROM '  . $model;
		if (!empty($id)) $query .= ' WHERE id = '. $db->quote($id);
		$results = $db->query($query);
		if ($results === false) {
			// query error
			echo '{"status":0,"message":"Query error.';
			if (DEBUG_MODE) echo ' Query: '.$query;
			echo '"}';
			break; // terminate case
		}

		// output as JSON string.

		echo '{"status":1,"message":"Ok","data":[';
		$r = 0;
		while ($row = $results->fetch(PDO::FETCH_ASSOC)) {
			if ($r++ > 0) echo ',';

			/*
			echo '{"id":"'.$row['id'].'",'.
				  '"name":"'.$row['name'].'",'.
				  '"description":"'.$row['description'].'",'.
				  '"user_id":"'.$row['user_id'].'"}';
			*/

			echo '{';
			$c = 0;
			foreach($modelFields[$model] as $colName) {
				if ($c++ > 0) echo ',';
				echo '"'.$colName.'":"'.$row[$colName].'"';
			}
			echo '}';

		}

		echo ']}';

	break;

	case 'save':
	// creates a new record if no id is passed or updates a record with a given id

		// store the POSTed form data into another array. Presumably we do additional
		// preprocessing here, such as data validation, which is omitted here for
		// brevity and clarity. 

		$fields = $_POST;

		// build query
		// we'll ignore validation but not sanitization
		if (empty($id)) {
			$query = 'INSERT INTO ';
		} else {
			$query = 'UPDATE ';
		}
		
		$query .= $model . ' SET ';
		// loop through field array, sanitize, and add to query
		$c = 0; // throwaway counter
		foreach ($fields as $fieldName => $fieldValue) {
			$query .= $c++ > 0 ? ", " : "";
			$query .= $fieldName . " = " . $db->quote($fieldValue);
		}
		
		if (!empty($id)) $query .= ' WHERE id = ' . $id;
		
		$result = $db->query($query); // send query to server
		
		if ($result === false) {
			echo '{"status":0,"message":"Query error.';
			if (DEBUG_MODE) echo ' Query: '.$query;
			echo '"}';
			break;
		}
		if ($result->rowCount() != 1) {
			echo '{"status":0,"message":"No rows modified.';
			if (DEBUG_MODE) echo ' Query: '.$query;
			echo '"}';
			break;
		}
		
		// no data to return, just return true
		echo '{"status":1,"message":"Record saved"}';

	break;

	case 'delete':
	// deletes a record with a given id

		if (empty($id)) {
			echo '{"status":0,"message":"No id specified"}';
			break;
		}

		$query = 'DELETE FROM '.$model.' WHERE id = ' . $id;

		if ($result === false) {
			echo '{"status":0,"message":"Query error.';
			if (DEBUG_MODE) echo ' Query: '.$query;
			echo '"}';
			break;
		}
		
		// no data to return, just return true
		echo '{"status":1,"message":"Record deleted"}';

	break;

	default:
		echo '{"status":0,"message":"Bad request sent"}';
	break;
} // switch $model


