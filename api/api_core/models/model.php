<?php
/*********
 * model.php - Model file
 * @author: Chris Langtiw
 *
 * @description: 
 * 
 ****/


/****
  The base Model class that all other models inherit from
  Defines the base methods
 **/
class Model {

	protected $modelName = NULL;
    public $parentModel = NULL;
    public $subModel = NULL;
    protected $fieldList = array();
    protected $primaryKey = NULL;
    protected $db = NULL;

    public $tableName = NULL;                       // table name in db
    protected $requestParams = array();				// Request parameter array for this model
    protected $relationships = array();				// holds array of table relationships

    /****
        The relationships get defined using the following structure:

	    protected $relationships = array(
	    	//'relation' => array(						// 'has', 'belongsTo', 'HABTM'
		        'alias' => array(                       // Alias to use for this table (use model name if no alias)
		            'model'     => 'modelClass',        // Name of the model (or model on other side of link table)
		            'localKey'  => 'foreignKeyField',   // Foreign key field name for local model
		            'remoteKey' => 'remoteForeignKey'   // The foreign key field name for the related model
		            'linkTable' => 'linkTableName'      // Name of link table to use
		        )
	        //)
	    );

     **/

    /****
      a reference to the database connection object is passed to the constructor and stored locally
      as dependency injection, as are the request parameters.

      Takes the parsed request parameters and instantiates the models required by the request. 
      Request parameters are stored in each model in the chain. These options can be changed or 
      overridden by method calls.
     **/
    function __construct(&$db, $requestParams, &$parentModel = NULL) {
        $this->parentModel = $parentModel;
        $this->db = $db;
        $this->modelName = strtolower(get_class($this));

        if (empty($this->fieldList)) {
            $this->loadFieldList();
        }

        // find primary key
        foreach($this->fieldList as $field) {
            if ($field['Key'] == 'PRI') {
                $this->primaryKey = $field['Field'];
                break;
            }
        }
        
        // attach request parameters to model
        $this->requestParams = array_shift($requestParams);

        // if there's more in the requestParams array, instantiate the next model and attach
        if (!empty($requestParams)) {
        	$subModelName = array_shift(array_keys($requestParams));
        	$this->subModel = new $subModelName($db, $requestParams, $this);
        }



    } // __construct

    /****
      Test method.
     **/
    public function test($params = array()) {
    	// override request params with passed params (if any)
    	$requestParams = array_merge($this->requestParams, $params);

    	return array((int)microtime(true), 'Test Method called on model '.get_class($this).'.');
    } // test

    /****
      Returns the request parameters array for this model (if any)
     **/
    public function getRequestParams() {
    	return $this->requestParams;
    } // getRequestParams

    /****
      Returns the request parameters array for the entire chain
      returns as both associative and numeric indexed array for convenience
     **/
    public function getRequestParamsChain() {
    	$requestParams = array();
    	//$c = 0;

    	$rootModel = $this->getRootModel();
    	$requestParams[strtolower(get_class($rootModel))] = $rootModel->requestParams;
     	//$requestParams[$c++] = $rootModel->requestParams;
   	
    	while ($rootModel->subModel != NULL) {
    		$rootModel = $rootModel->subModel;
    		$requestParams[strtolower(get_class($rootModel))] = $rootModel->requestParams;
    		//$requestParams[$c++] = $rootModel->requestParams;
    	}

    	return $requestParams;
    } // getRequestParams

    /****
      Finds the root of a chain of connected models
     **/
    public function getRootModel() {
        $rootModel = $this;
        while ($rootModel->parentModel != NULL) {
            $rootModel = $rootModel->parentModel;
        }

        return $rootModel;
    }

    /****
      Returns a specified model from the array of attached models
      Remember, only up to two models connected in any request is supported
     **/
    protected function getModel($modelName) {
        if (strtolower($this->modelName) == strtolower($modelName)) return $this;
        if ($this->parentModel != NULL && strtolower($this->parentModel->modelName) == strtolower($modelName)) return $this->parentModel;
        if ($this->subModel != NULL && strtolower($this->subModel->modelName) == strtolower($modelName)) return $this->subModel;
    }

    /****
      Looks up model given an alias (or returns entire list if no alias given)
     **/
    public function findModelAlias($params = NULL) {
        if (count($params) > 1) {
            throw new Exception("Incorrect number of parameters for ".get_class($this)."->getOne");
        }

        if (empty($params)) return $this->relatedModels;

        foreach($this->relatedModels as $model => $aliasList) {
            if (in_array($params[0], $aliasList)) return $model;
        }
    }

    /****
      Loads model table field names array from cache file or creates cache file if it doesn't exist
     **/
    protected function loadFieldList() {
        if (file_exists(CACHE_DIR.DS.strtolower(get_class($this)).'.dat')) {
            $this->fieldList = loadData(CACHE_DIR.DS.strtolower(get_class($this)).'.dat');
            return;
        }

        $results = $this->db->query('DESCRIBE '.$this->tableName);
        $this->fieldList = $results->fetchAll(PDO::FETCH_ASSOC);
        saveData($this->fieldList, CACHE_DIR.DS.strtolower(get_class($this)).'.dat');

    } // loadFieldList

    /****
      Returns dump of fieldList
     **/
    public function getFieldList() {
        if (empty($this->fieldList)) return false;

        return $this->fieldList;

    } // getFieldList

    /****
      -
     **/
    public function count($params = NULL) {

    } // count

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
	public function getQueryComponents($queryArray = array()) {
		// initialize query component array if it doesn't exist
		if (empty($queryArray)) $queryArray = array();
		if (!isset($queryArray['fields'])) $queryArray['fields'] = array();
		if (!isset($queryArray['joins'])) $queryArray['joins'] = array();
		if (!isset($queryArray['where'])) $queryArray['where'] = array();
		if (!isset($queryArray['order'])) $queryArray['order'] = array();
		if (!isset($queryArray['limit'])) $queryArray['limit'] = array();

		// set initial value for FROM clause
		if ($this->parentModel == null) {
			$queryArray['from'] = $this->tableName;
		}

		// if fields haven't been specified add all fields
		// only do this for the submodel or single models; parentmodel only gets listed explicity
		if (empty($this->requestParams['fields'])) {
			if ( ($this->parentModel == null && $this->subModel == null) ||
				 ($this->parentModel != null && $this->subModel == null)
				) {
				foreach ($this->getFieldList() as $field) {
					array_push($queryArray['fields'], $this->tableName.'.'.$field['Field'].' AS '."'".$this->tableName.'.'.$field['Field']."'");
				}
			}

		} else {
			// only add fields specified in requestParams
			foreach ($this->requestParams['fields'] as $field) {
				array_push($queryArray['fields'], $this->tableName.'.'.$field.' AS '."'".$this->tableName.'.'.$field."'");
			}
		}

		// set WHERE clause to id if set
		//if ($this->requestParams['id'] != null) {
		if (!empty($this->requestParams['id'])) {
			array_push(
				$queryArray['where'], 
				$this->tableName.'.'.$this->primaryKey.' = '.$this->requestParams['id']
			);
		}

		// if this is a single-model request, the FROM stays intact. However, when a 
		// submodel is involved (CIC or CC) then the
		if ($this->subModel != null && $this->tableName != $queryArray['from']) { 

		}
		
		if ($this->subModel != null) {
			// add table to join. Key is table name; value is relationship as string
			// 'table' => 'table1.field = table2.field'
			$localTable = $this->tableName;
			$remoteTable = $this->subModel->tableName;
			if (empty($this->relationships[$remoteTable]['linkTable'])) {
				$localTable =  $this->tableName;
			} else {
				$queryArray['from'] = $this->relationships[$remoteTable]['linkTable'];
				$localTable = $this->relationships[$remoteTable]['linkTable'];

			}

			array_push(
				$queryArray['joins'], 
			    array($remoteTable => $localTable.'.'.$this->relationships[$remoteTable]['localKey'].' = '.$remoteTable.'.'.$this->relationships[$remoteTable]['remoteKey'])
			);
			 
		}

		if ($this->subModel != null) {
			return $this->subModel->getQueryComponents($queryArray);
		} 

		return $queryArray;
	} // getQueryComponents

	public function queryTest() {
		// testing building a SELECT

		// get query components array
		$queryData = $this->getQueryComponents();

		$query = 'SELECT ';
		
		// add fields
		$c = 0;
		foreach ($queryData['fields'] as $field) {
			if ($c++ > 0) $query .= ', ';
			$query .= $field;
		}

		// add FROM clause
		$query .= ' FROM ' . $queryData['from'];

		// add JOIN clause
		if (!empty($queryData['joins'])) {
			$query .= ' INNER JOIN ';
			foreach ($queryData['joins'][0] as $table => $relationship) {
				$query .= $table . ' ON ' . $relationship;
			}
		}

		// add WHERE clause
		$c = 0;
		if (!empty($queryData['where'])) {
			$query .= ' WHERE ';
			foreach ($queryData['where'] as $field) {
				if ($c++ > 0) $query .= ' AND ';
				$query .= $field;
			}

		}

		// query built, now send to server
		$results = $this->db->query($query);

		// if there's a query error terminate with error status
		if ($results == false) {
			Message::stopError(400,'Query error. Please check request parameters.');
		}

		// if the result set is empty terminate with error status
		if ($this->db->query("SELECT FOUND_ROWS()")->fetchColumn() == 0) {
			Message::stopError(404,'No results matching your request were found.');
		}

		return $results->fetchAll(PDO::FETCH_ASSOC);


		//$queryData['processedQuery'] = $query;
		//return $queryData;
	} // queryTest


   /****
     REST API methods
     ----------------
     The following methods correspond to the HTTP methods supported by the API.
     Each of these methods should set a proper HTTP 1.1 status code as part of the response.
     See http://tools.ietf.org/html/rfc7231
    **/

    /****
      GET - Retrieve either a resource collection or an individual resource if a key is given

      Status Codes 		Condition
      200 OK 			Successful request
      404 Not Found 	When query returns an empty set
      400 Bad Request 	Improper parameters sent or query error

     **/
    public function get($params = array()) {
    	// override request params with passed params (if any)
    	//$requestParams = array_merge($this->requestParams, $params);

    	$requestParams = $this->getRequestParamsChain();

    	// get list of collections from request and get request pattern
    	// also get any explicitly defined fields for display
    	$models = array();
    	$requestPattern = NULL;
    	$fields = array();

    	foreach ($requestParams as $modelName => $param) {
    		array_push($models, $modelName);
    		$tableName = $this->getModel($modelName)->tableName;
    		$requestPattern = $param['requestPattern'];
    		if (!empty($param['fields'])) {
    			foreach ($param['fields'] as $field) {
    				array_push($fields, $tableName.'.'.$field.' AS '."'".$tableName.'.'.$field."'");
    			}
    		}
    	}
    	reset($requestParams); // reset pointer back to beginning of array

    	// determine the target and related models based on the request pattern
    	switch(true) {
    		case $requestPattern == 'C' || $requestPattern == 'CI':
    			$targetModelName = $models[0];
    			$targetTable = $this->getModel($targetModelName)->tableName;
    			$relatedModelName = NULL;
    			$relatedTable = NULL;
    		break;

    		case $requestPattern == 'CIC' || $requestPattern == 'CC':
    			$targetModelName = $models[1];
     			$targetTable = $this->getModel($targetModelName)->tableName;
	   			$relatedModelName = $models[0];
    			$relatedTable = $this->getModel($relatedModelName)->tableName;

    		break;
    	}

    	// if the target model doesn't have a field list defined, get all fields
		if (empty($requestParams[$targetModelName]['fields'])) {
			foreach ($this->getModel($modelName)->getFieldList() as $field) {
				array_push($fields, $targetTable.'.'.$field['Field'].' AS '."'".$targetTable.'.'.$field['Field']."'");
			}
		}

		// begin building query
    	$query = 'SELECT '. join(',',$fields);

		switch($requestPattern) {
			case 'C':
				$query .= " FROM {$targetTable}";
			break;
			
			case 'CI':
				$id = $requestParams[$targetModelName]['id'];
				$query .= " FROM {$targetTable} WHERE id = {$id}";
			break;
			
			case 'CIC':
				$id = $requestParams[$relatedModelName]['id'];
				
				// check the target model's relationship to the related model
				if (!empty($this->getModel($targetModelName)->relationships[$relatedModelName]['linkTable'])) {
					$relatedTable = $this->getModel($targetModelName)->relationships[$relatedModelName]['linkTable'];
				}

				$localKey = $this->getModel($targetModelName)->relationships[$relatedModelName]['localKey'];
				$remoteKey = $this->getModel($targetModelName)->relationships[$relatedModelName]['remoteKey'];
				
				$query .= " FROM {$targetTable} INNER JOIN {$relatedTable} ON {$targetTable}.{$localKey} = {$relatedTable}.{$remoteKey} WHERE {$targetTable}.{$localKey} = {$id}";
			break;
			
			case 'CC':
				// check the target model's relationship to the related model
				if (!empty($this->getModel($targetModelName)->relationships[$relatedModelName]['linkTable'])) {
					$relatedTable = $this->getModel($targetModelName)->relationships[$relatedModelName]['linkTable'];
				}

				$localKey = $this->getModel($targetModelName)->relationships[$relatedModelName]['localKey'];
				$remoteKey = $this->getModel($targetModelName)->relationships[$relatedModelName]['remoteKey'];
				
				$query .= " FROM {$targetTable} INNER JOIN {$relatedTable} ON {$targetTable}.{$localKey} = {$relatedTable}.{$remoteKey}";
			break;
		} // switch
   	
    	//if (DEBUG_MODE) Message::addDebugMessage('fieldList', $this->getFieldList());
    	if (DEBUG_MODE) Message::addDebugMessage('queryString', $query);

		// query built, now send to server
		$results = $this->db->query($query);

		// if there's a query error terminate with error status
		if ($results == false) {
			Message::stopError(400,'Query error. Please check request parameters.');
		}

		// if the result set is empty terminate with error status
		if ($this->db->query("SELECT FOUND_ROWS()")->fetchColumn() == 0) {
			Message::stopError(404,'No results matching your request were found.');
		}

		// retrieve result set from db
		$data = $results->fetchAll(PDO::FETCH_ASSOC);

		// if there were any tables listed in the attach query parameter, do separate queries for those
		// and attach to results

		// coming soon

		return $data;

    } // get

    /****
      HEAD - Retrieve either a resource collection or an individual resource if a key is given
      without the representation data (schema and headers/links only)

      Status Codes 		Condition
      200 OK 			Successful request

     **/
    public function head($params = array()) {
    	// override request params with passed params (if any)
    	$requestParams = array_merge($this->requestParams, $params);

    	return array((int)microtime(true), 'HEAD Method called.');
    } // head

    /****
      POST - Create a new resource in a given collection with complete data
     **/
    public function post($params = array()) {
    	// override request params with passed params (if any)
    	$requestParams = array_merge($this->requestParams, $params);

    	return array((int)microtime(true), 'POST Method called.');
    } // post

    /****
      PUT - Update a resource in a collection with a given id with complete data
     **/
    public function put($params = array()) {
    	// override request params with passed params (if any)
    	$requestParams = array_merge($this->requestParams, $params);

    	return array((int)microtime(true), 'PUT Method called.');
    } // put

    /****
      PATCH - Update a resource in a collection with a given id with partial data
     **/
    public function patch($params = array()) {
    	// override request params with passed params (if any)
    	$requestParams = array_merge($this->requestParams, $params);

    	return array((int)microtime(true), 'PATCH Method called.');
    } // patch

    /****
      DELETE - Delete a resource in a collection with a given id
     **/
    public function delete($params = array()) {
    	// override request params with passed params (if any)
    	$requestParams = array_merge($this->requestParams, $params);

    	return array((int)microtime(true), 'DELETE Method called.');
    } // delete

   

} // class Model

