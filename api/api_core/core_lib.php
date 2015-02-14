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
    if (file_exists(API_CORE_PATH.DS.'models'.DS.$class.'.php')) {
	    include (API_MODEL_PATH.$class.'.php');
	} else {
		header("HTTP/1.1 400 Bad Request");
		Message::addResponseKey('error_message','Requested resource model does not exist: '.API_CORE_PATH.DS.'models'.DS.$class.'.php');
		Message::render($useJsonEnvelope);
		exit();
	}
});


/****
  Shortcut to dump array values
 **/
function pr($srcArray) {
    return '<pre>'.print_r($srcArray,true).'</pre>';
}

/****
  Swap two variable values
 **/
function swap(&$value1, &$value2) {
    $tmp = $value1;
    $value1 = $value2;
    $value2 = $tmp;
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
	static function parsePath($path, $useModelKey = true) {
		$result = array();
		$paramsArray = explode(';', $path);
		
		// if $useModelKey is true take the first parameter and save it
		// in a 'model' key
		if ($useModelKey) $result['model'] = array_shift($paramsArray);

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
		$lastModel = null;
		$parsedRequestParams = array(); // save a copy of the fully parsed request parameters
		$pattern = ''; // string to identify request pattern (C, CI, CIC, CC, etc.)

		while ($path = array_shift($requestParams)) {
			
			$params = Dispatcher::parsePath($path);

			// this is a bit of a cheap shot. There should be a cleaner solution than
			// testing for file existence. Between this and the class autoloader there
			// must be a better solution.
			// I use file_exists here because class_exists() invokes the autoloader.
			if (file_exists(API_MODEL_PATH.$params['model'].'.php')) {
				// if this is a valid class, we set the model name
				$model = $params['model'];
				$parsedRequestParams[$model] = $params;
				$pattern .= 'C'; 
			} else {
				// otherwise we assume it's a resource identifier
				// (if the resulting query craps out later we generate a 400 Bad Request at that time)
				// rename the model property to id and copy over to current model
				$params['id'] = $params['model'];
				unset($params['model']);
				foreach ($params as $key => $value) {
					$parsedRequestParams[$lastModel][$key] = $value;
				}
				$pattern .= 'I';
			}
			// if the first parameter isn't a model then we can stop here with a bad request
			if (empty($model)) {
				Message::stopError(400,'Invalid URI');
			}

			$lastModel = $model;
		} // while

		// set the pattern as an element in each request segment
		foreach ($parsedRequestParams as $model => $segment) {
			$parsedRequestParams[$model]['requestPattern'] = $pattern;
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
		$model = new $modelName($db, $parsedRequestParams);

		//if (DEBUG_MODE) Message::addDebugMessage('parentModel', $model->parentModel->tableName);
		//if (DEBUG_MODE) Message::addDebugMessage('subModel', $model->subModel->tableName);
		//if (DEBUG_MODE) Message::addDebugMessage('modelRequestParams', $model->getRequestParams());
		if (DEBUG_MODE) Message::addDebugMessage('requestParamsChain', $model->getRequestParamsChain());
		//if (DEBUG_MODE) Message::addDebugMessage('modelFieldList', $model->getFieldList());
		//if (DEBUG_MODE) Message::addDebugMessage('queryTest', $model->queryTest());

		// Route the processing to the appropriate Model method
		// the simplest approach would be to name methods on the model after the request
		// methods - get, post, put, patch, and delete. 

		// first test if the method is allowed

		$method = strtolower($_SERVER['REQUEST_METHOD']);
		$requestData = $model->{$method}();

		Message::setResponse($requestData); // sets response body data
		Message::render(); // render output to user agent

	} // route


} // Dispatcher

/****
  Static output class
  Message encapsulates output into a single static class. Because output is via JSON and HTTP 
  headers instead of HTML, data is stored as keys inside associative arrays which get converted 
  to JSON when render() is called.
 **/
class Message {

	static protected $response = array();
	static protected $headers = array();
	static protected $debugMessages = array();
	static protected $data = null;
	static public $useEnvelope;

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
	static public function addResponseKey($key, $data) {
		Message::$response[$key] = $data;
	}

	/****
	  Set the entire response body array by replacing it with array $data. If an 
	  envelope is used the data is placed inside a 'response' property of the 
	  envelope.
	 **/
	static public function setResponse($data) {
		Message::$response = $data;
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
	  Sets HTTP Response Code
	 **/
	static public function setHTTPStatus($statusCode) {
		$httpCodes = array(
		    100 => 'Continue',
		    101 => 'Switching Protocols',
		    102 => 'Processing',
		    200 => 'OK',
		    201 => 'Created',
		    202 => 'Accepted',
		    203 => 'Non-Authoritative Information',
		    204 => 'No Content',
		    205 => 'Reset Content',
		    206 => 'Partial Content',
		    207 => 'Multi-Status',
		    300 => 'Multiple Choices',
		    301 => 'Moved Permanently',
		    302 => 'Found',
		    303 => 'See Other',
		    304 => 'Not Modified',
		    305 => 'Use Proxy',
		    306 => 'Switch Proxy',
		    307 => 'Temporary Redirect',
		    400 => 'Bad Request',
		    401 => 'Unauthorized',
		    402 => 'Payment Required',
		    403 => 'Forbidden',
		    404 => 'Not Found',
		    405 => 'Method Not Allowed',
		    406 => 'Not Acceptable',
		    407 => 'Proxy Authentication Required',
		    408 => 'Request Timeout',
		    409 => 'Conflict',
		    410 => 'Gone',
		    411 => 'Length Required',
		    412 => 'Precondition Failed',
		    413 => 'Request Entity Too Large',
		    414 => 'Request-URI Too Long',
		    415 => 'Unsupported Media Type',
		    416 => 'Requested Range Not Satisfiable',
		    417 => 'Expectation Failed',
		    //418 => 'I\'m a teapot',
		    422 => 'Unprocessable Entity',
		    423 => 'Locked',
		    424 => 'Failed Dependency',
		    425 => 'Unordered Collection',
		    426 => 'Upgrade Required',
		    449 => 'Retry With',
		    450 => 'Blocked by Windows Parental Controls',
		    500 => 'Internal Server Error',
		    501 => 'Not Implemented',
		    502 => 'Bad Gateway',
		    503 => 'Service Unavailable',
		    504 => 'Gateway Timeout',
		    505 => 'HTTP Version Not Supported',
		    506 => 'Variant Also Negotiates',
		    507 => 'Insufficient Storage',
		    509 => 'Bandwidth Limit Exceeded',
		    510 => 'Not Extended'
		);
		// set HTTP status code
		header("HTTP/1.1 ".$statusCode." ".$httpCodes[$statusCode]);

	}

	/****
	  Terminates API response with a status code and error message
	 **/
	static public function stopError($errorCode, $errorMessage) {
		Message::setHTTPStatus($errorCode);
		Message::addResponseKey('errorMessage', $errorMessage);
		Message::render();
		exit;
	}

	/****
	  Renders the queued data to the user agent.
	  NOTE: Adding debug messages will always cause an envelope to be used 
	  regardless of the DEBUG_MODE or $useEnvelope settings.
	 **/
	static public function render($useEnvelope = null) {
		if (empty($useEnvelope)) $useEnvelope = Message::$useEnvelope;

		header('Content-Type: application/json');
		
		if ($useEnvelope || !empty(Messages::$debugMessages)) {
			// if an envelope is used the header values will be placed in a wrapper
			// and the data will be placed in a 'Response' property
			Message::$data = array(
				'response' => Message::$response
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

