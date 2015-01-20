<?php
/*****
  core_classes.php - API Core Classes 
 **/

// base classes

class Authenticate {

}

class Dispatcher {

	static $data = array();

	static function parseRequest($request) {
		//echo preg_quote(URI_BASE, '/');
		/*
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
		*/
		// strip out the URI base path from the request URI
		$paramString = preg_replace('/'.preg_quote(URI_BASE, '/').'/', '', $request);
		// also strip off the query string off the string as that data is available via $_GET
		$paramString = explode('?', $paramString)[0]; // return the first element and dump the query string
		// break out the parameters into an array
		$params = explode('/', $paramString);

		//if (DEBUG_MODE) echo '<pre>'.print_r($params,true).'</pre>'; // dunp $params for inspection
		return $params;
	}

	/***
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
	 */
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

	/***
	   route() is responsible for 'routing' the request. It takes the request params array
	   and parses each path segment, instantiating the appropriate models and calling the 
	   method based on the request type.
	   
	 */
	static function route($requestParams) {
		if (DEBUG_MODE) global $output;

		$c = 0;
		$lastModel = null;

		$parsedRequestParams = array(); // save a copy of the fully parsed request parameters

		while ($path = array_shift($requestParams)) {
			
			$params = Dispatcher::parsePath($path);

			//if ($c > 0) $lastModel = &$model;
			//if (class_exists($params['model'])) {

			// this is a bit of a cheap shot. There should be a cleaner solution than
			// testing for file existence. Between this and the class autoloader there
			// must be a better solution.
			if (file_exists(API_CORE_PATH.DS.'models'.DS.$params['model'].'.php')) {
				// if this is a valid class, we load the class
				$model = new $params['model']($lastModel);
				$parsedRequestParams[$params['model']] = $params;

				//$output->addDebugMessage($params['model'].'_parent', empty($lastModel) ? null : $lastModel->tableName);
				if (DEBUG_MODE) $output->addDebugMessage($params['model'].'_parent', $model->parentCollection == null ? null : $model->parentCollection->tableName);
			} else {
				// otherwise we assume it's a resource identifier
				// (if the resulting query craps out later we generate a 400 Bad Request)
				$lastModel->setID($params['model']); // set the previous path's resource identifier
				
				// rename the model property to id and copy over to current model
				$params['id'] = $params['model'];
				unset($params['model']);
				foreach ($params as $key => $value) {
					$parsedRequestParams[strtolower(get_class($lastModel))][$key] = $value;
				}
				//$parsedRequestParams[strtolower(get_class($lastModel))]['id'] = $params['model'];



				if (DEBUG_MODE) $output->addDebugMessage($lastModel->tableName.'_id', $lastModel->id);
			}

			if (DEBUG_MODE) $output->addDebugMessage('parsed_params'.$c, $params);

			if (DEBUG_MODE) $output->addDebugMessage('model'.$c, $model->tableName);
			$c++;
			

			$lastModel = &$model;
		}

		if (DEBUG_MODE) $output->addDebugMessage('parsedRequestParams', $parsedRequestParams);

		if (DEBUG_MODE) $output->addDebugMessage('queryTest', $model->queryTest());


	}
}

class Message {

	protected $message = array();
	protected $headers = array();
	protected $debugMessages = array();

	public function addHeader($key, $data) {
		$this->headers[HEADER_PREFIX . $key] = $data;
	}

	public function addMessage($key, $data) {
		$this->message[$key] = $data;
	}

	public function addDebugMessage($key, $data) {
		$this->debugMessages[$key] = $data;
	}

	public function render($useEnvelope) {
		header('Content-Type: application/json');
		
		if ($useEnvelope) {
			// if an envelope is used the header values will be placed in a wrapper
			// and the data will be placed in a 'Response' property
			$this->data = array(
				'Response' => $this->message
			);
	
			foreach ($this->headers as $header => $headerValue) {
				$this->data[$header] = $headerValue;
			}
			$this->data[HEADER_PREFIX.'Request-Duration'] = (int)((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000) . 'ms';

		} else {
			// if no wrapper is used, the header values will be sent as proper response
			// headers to the client
			foreach ($this->headers as $header => $headerValue) {
				header($header .': '. $headerValue);
			}
			header(HEADER_PREFIX.'Request-Duration: '. (int)((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000) . 'ms');
			$this->data = $this->message;
		}

		if (DEBUG_MODE) {
			$this->data['debug'] = array();
			foreach ($this->debugMessages as $header => $headerValue) {
				$this->data['debug'][$header] = $headerValue;
			}
		}
		echo json_encode($this->data);
	} // render
}

class Model {
	public $parentCollection = null;
	public $subCollection = null;
	public $tableName = null;
	protected $primaryKey = 'id';
	protected $fields = array();
	protected $relationships = array();
	public $id = null;

	function __construct(&$parentCol = null) {
		if (!empty($parentCol)) {
			$this->parentCollection = $parentCol;
			$parentCol->setSubCollection($this);
		}
	}

	function setID($id = null) {
		$this->id = $id;
	}

	function setSubCollection(&$subCol) {
		$this->subCollection = $subCol;
	}

	/***
	   The idea behind building the queries is to recurse through the model chain, constructing
	   an array in the following structure:

	   query: {
		   fields: [ field1, field2, ... ],
		   from: origin_table,
		   joins: [
		      { table: relationship },
		      ...
		   ],
		   where: [ clause1, clause2, ... ],
		   order: [
		      { field: direction },
		      ...
		   ],
		   limit: [ range, offset ]
	   }

	   Using this structure, the components of the query can be added in any order, and later
	   parsed into a proper query string
	 */
	function queryTest($queryArray = array()) {

		if (empty($queryArray)) $queryArray = array();

		if (!isset($queryArray['fields'])) $queryArray['fields'] = array();
		if (!isset($queryArray['joins'])) $queryArray['joins'] = array();
		if (!isset($queryArray['where'])) $queryArray['where'] = array();
		if (!isset($queryArray['order'])) $queryArray['order'] = array();
		if (!isset($queryArray['limit'])) $queryArray['limit'] = array();

		foreach ($this->fields as $field) {
			array_push($queryArray['fields'], $this->tableName.'.'.$field.' AS '.$this->tableName.'_'.$field);
		}

		if ($this->id != null) {
			array_push(
				$queryArray['where'], 
				$this->tableName.'.'.$this->primaryKey.' = '.$this->id
			);
		}
		
		if ($this->subCollection != null) {
			array_push(
				$queryArray['joins'], 
			    array($this->subCollection->tableName => $this->relationships[$this->subCollection->tableName])
			); 
		}

		if ($this->parentCollection != null) {
			return $this->parentCollection->queryTest($queryArray);
		} else {
			$queryArray['from'] = $this->tableName;
			return $queryArray;
		}
	} // queryTest
}

