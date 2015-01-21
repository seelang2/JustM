<?php
/*********
 * @name: core_lib.php - API Core Library module
 * @author: Chris Langtiw
 *
 * @description: Contains functions and base classes used in API core
 * 
 ****/
   

// register class autoloader
spl_autoload_register(function ($class) {
//    if (file_exists(API_CORE_PATH.DS.'models'.DS.$class.'.php')) {
	    include (API_MODEL_PATH.$class.'.php');
//	} else {
//		header("HTTP/1.1 400 Bad Request");
//		Message::addMessage('error_message','Requested resource model does not exist: '.API_CORE_PATH.DS.'models'.DS.$class.'.php');
//		Message::render($useJsonEnvelope);
//		exit();
//	}
});


/****
  Shortcut to dump array values
 **/
function pr($srcArray) {
    return '<pre>'.print_r($srcArray,true).'</pre>';
}

/****
  write to serialized file
 **/
function saveData($data, $datafile) {
    $OUTPUT = serialize($data); 
    $fp = fopen($datafile,"w"); 
    fputs($fp, $OUTPUT); 
    fclose($fp); 
}

/****
  read from serialized file
 **/
function loaddata($datafile) {
    $data = unserialize(file_get_contents($datafile));
    return $data;
}



// static classes

class Authenticate {

}

class Dispatcher {

	static $data = array();

	/****
	  Parse out the request URI into a base array. Ex: Given the following URI:

	  http://localhost/chrisrichron/api_v3/this/is;param1=val1;param2=val2/a/test;options=opt1,opt2,opt3?q=and+a+query+string+too&x=23

	  The resulting array is as follows: 
	  Array
	  (
	    [0] => this
	    [1] => is;param1=val1;param2=val2
	    [2] => a
	    [3] => test;options=opt1,opt2,opt3
	  )
	**/
	static function parseRequest($request) {
		//echo preg_quote(URI_BASE, '/');
		// strip out the URI base path from the request URI
		$paramString = preg_replace('/'.preg_quote(URI_BASE, '/').'/', '', $request);
		// also strip off the query string off the string as that data is available via $_GET
		$paramString = explode('?', $paramString)[0]; // return the first element and dump the query string
		// break out the parameters into an array
		$params = explode('/', $paramString);

		//if (DEBUG_MODE) echo '<pre>'.print_r($params,true).'</pre>'; // dunp $params for inspection
		return $params;
	} // parseRequest

	/****
	  Take a delimited string and parse into components
	  We assume the first parameter in the path is a resource
	  identifier (collection name or item id) per the API spec.
	  The rest of the path should be parameters

	  ex: users;limit=20;fields=firstname,lastname,email
	  results in the following array: 
	  array {
		'model' => 'users',
		'limit' => 20,
		'fields' => array { 'firstname', 'lastname', 'email' }
	  }
	 **/
	static function parsePath($path) {
		$result = array();
		$paramsArray = explode(';', $path);
		
		$result['model'] = array_shift($paramsArray);

		foreach ($paramsArray as $paramString) {
			$paramSet = explode('=', $paramString);
			$valueArray = explode(',', $paramSet[1]);
			if (count($valueArray) == 1) {
				$result[$paramSet[0]] = $valueArray[0];
			} else {
				$result[$paramSet[0]] = $valueArray;
			}
		}
		return $result;
	} // parsePath

	/****

	 **/
	static function parseRequestParams($request_uri) {

		$requestParams = Dispatcher::parseRequest($request_uri);
		if (DEBUG_MODE) Message::addDebugMessage('request_params', $requestParams);

		$lastModel = null;

		$parsedRequestParams = array(); // save a copy of the fully parsed request parameters

		while ($path = array_shift($requestParams)) {
			
			$params = Dispatcher::parsePath($path);

			// this is a bit of a cheap shot. There should be a cleaner solution than
			// testing for file existence. Between this and the class autoloader there
			// must be a better solution.
			if (file_exists(API_MODEL_PATH.$params['model'].'.php')) {
				// if this is a valid class, we set the model name
				$model = $params['model'];
				$parsedRequestParams[$model] = $params;

			} else {
				// otherwise we assume it's a resource identifier
				// (if the resulting query craps out later we generate a 400 Bad Request at that time)
				// rename the model property to id and copy over to current model
				$params['id'] = $params['model'];
				unset($params['model']);
				foreach ($params as $key => $value) {
					$parsedRequestParams[$lastModel][$key] = $value;
				}

			}
			$lastModel = $model;
		}

		return $parsedRequestParams;
	} // parseResquestParams

	/****
	  Routes and processes request
	  This new version of route is more atomic. Its job is to parse the request, instantiate
	  the root model, call the appropriate model method to process the request, then deliver 
	  the output to the user agent. The router is pretty much the controller logic in the API
	  core. Most of the gruntwork like parsing and whatnot are handed off to helper functions
	  to keep the code cleaner. In addition, the Model is responsible for instantiating the 
	  appropriate related models based on the request parameters. 
	 **/
	static function route(&$db, $request_uri, $options = null) {

		$parsedRequestParams = Dispatcher::parseRequestParams($request_uri);
		if (DEBUG_MODE) Message::addDebugMessage('parsedRequestParams', $parsedRequestParams);

		// non-destructively retrieve the first key from the request params array
		$modelName = array_shift(array_keys($parsedRequestParams));

		// instantiate the root model and pass the request parameters into it
		$model = new $modelName($db);

		Message::addDebugMessage($modelName.'_related_models', $model->getRelatedModels());
		Message::addDebugMessage($modelName.'_related_data', $model->getAll());


	} // route


} // Dispatcher

/****
  Static output class
  Message encapsulates output into a single static class. Because output is via JSON and HTTP 
  headers instead of HTML, data is stored as keys inside associative arrays which get converted 
  to JSON when render() is called.
 **/
class Message {

	static protected $message = array();
	static protected $headers = array();
	static protected $debugMessages = array();
	static protected $data = null;

	/****
	  Sets HTTP headers to be sent to the user agent. The keys in $headers will be 
	  prefixed with the HEADER_PREFIX value. If an envelope is used, $headers will 
	  be attached to the envelope instead of being passed as HTTP headers.
	 **/
	static public function addHeader($key, $data) {
		Message::$headers[HEADER_PREFIX . $key] = $data;
	}

	/****
	  Adds a key to the response body. If an envelope is used the data is placed 
	  inside a 'response' property of the envelope.
	 **/
	static public function addMessage($key, $data) {
		Message::$message[$key] = $data;
	}

	/****
	  Allows non-body debug data to be included in the response. 
	  NOTE: Adding debug messages will always cause an envelope to be used 
	  regardless of the DEBUG_MODE or $useEnvelope settings.
	 **/
	static public function addDebugMessage($key, $data) {
		Message::$debugMessages[$key] = $data;
	}

	/****
	  Renders the queued data to the user agent.
	  NOTE: Adding debug messages will always cause an envelope to be used 
	  regardless of the DEBUG_MODE or $useEnvelope settings.
	 **/
	static public function render($useEnvelope) {
		header('Content-Type: application/json');
		
		if ($useEnvelope || !empty(Messages::$debugMessages)) {
			// if an envelope is used the header values will be placed in a wrapper
			// and the data will be placed in a 'Response' property
			Message::$data = array(
				'Response' => Message::$message
			);
	
			foreach (Message::$headers as $header => $headerValue) {
				Message::$data[$header] = $headerValue;
			}
			Message::$data[HEADER_PREFIX.'Request-Duration'] = (int)((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000) . 'ms';

		} else {
			// if no wrapper is used, the header values will be sent as proper response
			// headers to the client
			foreach (Message::$headers as $header => $headerValue) {
				header($header .': '. $headerValue);
			}
			header(HEADER_PREFIX.'Request-Duration: '. (int)((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000) . 'ms');
			Message::$data = $this->message;
		}

		if (DEBUG_MODE || !empty(Messages::$debugMessages)) {
			Message::$data['debug'] = array();
			foreach (Message::$debugMessages as $header => $headerValue) {
				Message::$data['debug'][$header] = $headerValue;
			}
		}
		echo json_encode(Message::$data);
	} // render
} // Message

