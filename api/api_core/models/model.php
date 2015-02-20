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
    
    /****
		List of possible request params
		model
		id
		requestPattern
		fields

     **/
    protected $requestParams = array();				// Request parameter array for this model
    
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
    protected $relationships = array();				// holds array of table relationships

    /****
      a reference to the database connection object is passed to the constructor and stored locally
      as dependency injection, as are the request parameters.

      Takes the parsed request parameters and instantiates the models required by the request. 
      Request parameters are stored in each model in the chain. These options can be changed or 
      overridden by method calls.
     **/
    function __construct(&$db, $requestParams = NULL, $globalParams = NULL, &$parentModel = NULL) {
        $this->parentModel = $parentModel;
        $this->db = $db;
        $this->modelName = strtolower(get_class($this));
        $this->globalParams = $globalParams;

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
        if (empty($requestParams)) {
        	$this->requestParams = array(
        		'model' 			=> $this->modelName,
        		'requestPattern' 	=> 'C',
        	);
        } else {
	        $this->requestParams = array_shift($requestParams);        	
        }

        // if there's more in the requestParams array, instantiate the next model and attach
        if (!empty($requestParams)) {
        	$subModelName = array_shift(array_keys($requestParams));
        	$this->subModel = new $subModelName($db, $requestParams, $globalParams, $this);
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
      Produces an array where each field has the following keys:
 
		{
			"Field": "id",
			"Type": "int(10) unsigned",
			"Null": "NO",
			"Key": "PRI",
			"Default": null,
			"Extra": "auto_increment"
		}

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
      Returns dump of fieldList array.
     **/
    public function getFieldList() {
        if (empty($this->fieldList)) return false;

        return $this->fieldList;

    } // getFieldList

    /****
      Returns PK for this model's table
     **/
    public function getPrimaryKey() {

        return $this->primaryKey;

    } // getPrimaryKey

    /****
      -
     **/
    public function count($params = NULL) {

    } // count

    /****
      Sanitize data for use in SQL query
     **/
    public function sanitize($data = NULL) {
    	if (empty($data)) return false;
    	return $this->db->quote($data);
    } // sanitize



   /****
     REST API methods
     ----------------
     The following methods correspond to the HTTP methods supported by the API.
     Each of these methods should set a proper HTTP 1.1 status code as part of the response.
     See http://tools.ietf.org/html/rfc7231
    **/

    /****
      GET - Retrieve either a resource collection or an individual resource if a key is given

      get() can also be passed an array that overrides the request params, which is useful when 
      creating separate unbound instances of a model. 

      Also, the 'id' element can be an array of values, which will then cause the query to use 
      'WHERE id IN(...)' referencing the array of ids.

      Status Codes 		Condition
      200 OK 			Successful request
      404 Not Found 	When query returns an empty set
      400 Bad Request 	Improper parameters sent or query error

     **/
    public function get($params = NULL) {
    	//if (DEBUG_MODE) Message::addDebugMessage('Model-get', 'called on model '.$this->modelName);
    	//$queryList = array();

     	// override request params with passed params (if any)
	   	//if ($params === NULL) {
	    	$requestParams = $this->getRequestParamsChain();
    	//} else {
    	//	$requestParams[strtolower(get_class($this))] = array_merge($this->requestParams, $params);
    	//	if (!empty($params['id'])) $requestParams[strtolower(get_class($this))]['requestPattern'] = 'CI';
    	//}

    	//if (DEBUG_MODE) Message::addDebugMessage('requestParams', $requestParams);

    	// get list of collections from request and get request pattern
    	// also get any explicitly defined fields for each model
    	$models = array();
    	$requestPattern = NULL;
    	$fields = array();

    	foreach ($requestParams as $tmpModelName => $tmpParam) {
    		array_push($models, $tmpModelName);
    		$tmpTableName = $this->getModel($tmpModelName)->tableName;
    		$requestPattern = $tmpParam['requestPattern'];
			$fields[$tmpModelName] = array();
    		if (!empty($tmpParam['fields'])) {
    			foreach ($tmpParam['fields'] as $tmpField) {
    				//array_push($fields, $tmpTableName.'.'.$tmpField.' AS '."'".$tmpTableName.'.'.$tmpField."'");
    				array_push($fields[$tmpModelName], "`".$tmpTableName."`".'.'."`".$tmpField."`");
    			}
    		}
    	}
    	reset($requestParams); // reset pointer back to beginning of array

    	// determine the target and related models based on the request pattern
    	switch(true) {
    		case $requestPattern == 'C' || $requestPattern == 'CI':
    			$targetModelName = $models[0];
    			$targetModel = $this->getModel($targetModelName);
    			$targetTable = $targetModel->tableName;
    			$relatedModelName = NULL;
    			$relatedModel = NULL;
    			$relatedTable = NULL;
    		break;

    		case $requestPattern == 'CIC' || $requestPattern == 'CC':
    			$targetModelName = $models[1];
      			$targetModel = $this->getModel($targetModelName);
	   			$targetTable = $targetModel->tableName;
	   			$relatedModelName = $models[0];
    			$relatedModel = $this->getModel($relatedModelName);
    			$relatedTable = $this->getModel($relatedModelName)->tableName;
    		break;
    	}

    	// add in the primary keys for the models for aggregation purposes
    	array_unshift($fields[$targetModelName], $targetTable.'.'.$targetModel->primaryKey.' AS PRI');
    	if ($relatedModelName != NULL) array_unshift($fields[$relatedModelName], $relatedTable.'.'.$relatedModel->primaryKey.' AS PRI');

    	// if the target model doesn't have a field list defined, get all fields
		if (empty($requestParams[$targetModelName]['fields'])) {
			foreach ($targetModel->getFieldList() as $field) {
				//array_push($fields, $targetTable.'.'.$field['Field'].' AS '."'".$targetTable.'.'.$field['Field']."'");
				array_push($fields[$targetModelName], "`".$targetTable."`".'.'."`".$field['Field']."`");
			}
		}

		//if (DEBUG_MODE) Message::addDebugMessage('fieldsArray', $fields);

    	// the join will be done manually, so we need to split this into multiple queries
    	// the first query will either be for the related model if any or just the target model
    	// so we use an alias for the first query model info
    	if ($relatedModel === NULL) {
    		$tmpModelName = $targetModelName;
    		$tmpModel = $targetModel;
    		$tmpTable = $targetTable;
    	} else {
    		$tmpModelName = $relatedModelName;
    		$tmpModel = $relatedModel;
    		$tmpTable = $relatedTable;
    	}

		// begin building query
    	$query = 'SELECT DISTINCT '. join(',',$fields[$tmpModelName]);

		switch($requestPattern) {
			case 'C':
				$query .= " FROM {$targetTable}";
			break;
			
			case 'CI':
				$id = $requestParams[$targetModelName]['id'];
				if (!is_array($id)) $id = $this->sanitize($id);
				
				if (is_array($id)) {
					$query .= " FROM {$targetTable} WHERE id IN(".join(',', $id).")";
				} else {
					$query .= " FROM {$targetTable} WHERE id = {$id}";
				}
			break;
			
			case 'CIC':
				$id = $requestParams[$relatedModelName]['id'];
				if (!is_array($id)) $id = $this->sanitize($id);
				
				// check the target model's relationship to the related model
				if (!empty($this->getModel($targetModelName)->relationships[$relatedModelName]['linkTable'])) {
					$relatedTable = $this->getModel($targetModelName)->relationships[$relatedModelName]['linkTable'];
				}

				$localKey = $this->getModel($targetModelName)->relationships[$relatedModelName]['localKey'];
				$remoteKey = $this->getModel($targetModelName)->relationships[$relatedModelName]['remoteKey'];
				
				//$query .= " FROM {$targetTable} INNER JOIN {$relatedTable} ON {$targetTable}.{$localKey} = {$relatedTable}.{$remoteKey} WHERE {$targetTable}.{$localKey} = {$id}";
				// using join decomposition so only perform the first query
				//$query .= " FROM {$relatedTable} WHERE id = {$id}";
				if (is_array($id)) {
					$query .= " FROM {$relatedTable} WHERE id IN(".join(',', $id).")";
				} else {
					$query .= " FROM {$relatedTable} WHERE id = {$id}";
				}
			break;
			
			case 'CC':
				// check the target model's relationship to the related model
				if (!empty($this->getModel($targetModelName)->relationships[$relatedModelName]['linkTable'])) {
					$relatedTable = $this->getModel($targetModelName)->relationships[$relatedModelName]['linkTable'];
				}

				$localKey = $this->getModel($targetModelName)->relationships[$relatedModelName]['localKey'];
				$remoteKey = $this->getModel($targetModelName)->relationships[$relatedModelName]['remoteKey'];
				
				//$query .= " FROM {$targetTable} INNER JOIN {$relatedTable} ON {$targetTable}.{$localKey} = {$relatedTable}.{$remoteKey}";
				// using join decomposition so only perform the first query
				//$query .= " FROM {$relatedTable}";
				$query .= " FROM {$relatedTable} INNER JOIN {$targetTable} ON {$targetTable}.{$localKey} = {$relatedTable}.{$remoteKey}";
			break;
		} // switch

		// check for limit query parameter and validate before using
		// there may be a faster way to do this
		if (!empty($this->globalParams['limit'])) {
			$limit = explode(',',$this->globalParams['limit']);
			// validate each limit parameter
			foreach($limit as $tmpParam) {
				if (!is_numeric($tmpParam)) {
					$limit = NULL; // just invalidate the entire limit param
					break; // terminate validation
				}
			}
		}
		
		// we want the limit to apply to the actual target model and not any related model
		// so for first query only apply limit if there is no related model
		if ( $relatedModel === NULL && (!empty($limit)) ) { 
			$query .= ' LIMIT '.join(',', $limit);
		}

		if (DEBUG_MODE) Message::addDebugMessage('queries', $query); // debug messages are now serialized
		//array_push($queryList, $query); // append query to query log

		// query built, now send to server
		$results = $this->db->query($query);

		// if there's a query error terminate with error status
		if ($results == false) {
 		   	//if (DEBUG_MODE) Message::addDebugMessage('queries', $query);
    		//if (DEBUG_MODE) Message::addDebugMessage('keys', $keys);
			Message::stopError(400,'Query error. Please check request parameters.');
		}

		// if the result set is empty terminate with error status
		if ($this->db->query("SELECT FOUND_ROWS()")->fetchColumn() == 0) {
 		   	//if (DEBUG_MODE) Message::addDebugMessage('queries', $query);
  		  	//if (DEBUG_MODE) Message::addDebugMessage('keys', $keys);
			Message::stopError(404,'No results matching your request were found.');
		}

		// parse data into more useful structure
		/*
			{
				collection: [
					{
						...
						subcollection: [
							{ }...
						]
					}
				]
			}

			the bigger question is how to get this to sort properly. The easiest way to do this would
			be to use join decomposition - break the query into multiple simplified queries and then 
			perform the join in the application logic. Can be more efficient as the database gets larger.
		*/

		$data = array();
		$data[$tmpModelName] = array();
		$keys = array();
		$keys[$tmpModelName] = array();

		// check and set attach query parameter data
		if ( (!empty($this->globalParams['attach'])) ) {
			$attachedModels = array(); // create empty bucket for valid attached models
			foreach (explode(',',$this->globalParams['attach']) as $attachedModelName) {
				// check the target model's relationship to the attached model to build query
				// if the models aren't related, don't bother and bail to next attached model
				if (empty($targetModel->relationships[$attachedModelName])) continue;
				array_push($attachedModels, $attachedModelName); // add to list of valid attached models
				// define a bucket for the attached table
				$data[$attachedModelName] = array();

				// get the attached model records based on target model PK using IN()
				$keys[$attachedModelName] = array();
				$keys[$attachedModelName]['RK'] = $targetModel->relationships[$attachedModelName]['remoteKey'];
				$keys[$attachedModelName]['LK'] = $targetModel->relationships[$attachedModelName]['localKey'];
			} // foreach
		}

		// loop through result set and parse
		while ($row = $results->fetch(PDO::FETCH_ASSOC)) {
			// extract PK and add to key list
			array_push($keys[$tmpModelName], $row['PRI']);
			$tmpId = $row['PRI'];
			unset($row['PRI']); // for internal use - only return requested fields

			// add the data rows if this is a single-collection request
			if ($requestPattern == 'C' || $requestPattern == 'CI') {
				foreach ($attachedModels as $attachedModelName) {
					$attachedModelLK = $keys[$attachedModelName]['LK'];
					$attachedModelRK = $keys[$attachedModelName]['RK'];
					//unset($keys[$attachedModelName]['LK']);
					//unset($keys[$attachedModelName]['RK']);

					//if (DEBUG_MODE) Message::addDebugMessage('messages', 'aLK = '.$attachedModelLK.', aRF = '.$attachedModelRK.', data = '.$row[$attachedModelLK]);

					if (!in_array($row[$attachedModelLK], $keys[$attachedModelName])) array_push($keys[$attachedModelName], $row[$attachedModelLK]);
				}

				array_push($data[$tmpModelName], $row);
			}

			// add the data rows plus FK 
			if ($requestPattern == 'CC' || $requestPattern == 'CIC') {
				$row[$remoteKey] = $tmpId;
				$row[$targetModelName] = array();
				array_push($data[$tmpModelName], $row);
			}

			// if there is a related model, get the FK and store the key
			//if ($relatedModel !== NULL) array_push($keys[$relatedModelName], $row[$remoteKey]);
			//if ($relatedModel !== NULL) array_push($keys['remoteKey'], $row[$localKey]);
		} // while row

		// if there's a related model get its data
		if ($relatedModel !== NULL) {
    		// point alias to target model
    		$tmpModelName = $targetModelName;
    		$tmpModel = $targetModel;
    		$tmpTable = $targetTable;

    		// add FK to result set
    		array_unshift($fields[$tmpModelName], $tmpTable.'.'.$localKey.' AS FK');

			// set up second query as manual inner join
	    	$query = 'SELECT '. join(',',$fields[$tmpModelName]).' FROM '.$tmpTable.' WHERE '.$localKey.' IN('.join(',', $keys[$relatedModelName]).')';

			if ( $relatedModel === NULL && (!empty($limit)) ) { 
				$query .= ' LIMIT '.join(',', $limit);
			}

			if (DEBUG_MODE) Message::addDebugMessage('queries', $query); // debug messages are now serialized

			// query built, now send to server
			$results = $this->db->query($query);

			// if there's a query error terminate with error status
			if ($results == false) {
	 		   	//if (DEBUG_MODE) Message::addDebugMessage('queries', $query);
	    		//if (DEBUG_MODE) Message::addDebugMessage('keys', $keys);
				Message::stopError(400,'Query error. Please check request parameters.');
			}

			// if the result set is empty terminate with error status
			if ($this->db->query("SELECT FOUND_ROWS()")->fetchColumn() == 0) {
	 		   	//if (DEBUG_MODE) Message::addDebugMessage('queries', $query);
	  		  	//if (DEBUG_MODE) Message::addDebugMessage('keys', $keys);
				Message::stopError(404,'No results matching your request were found.');
			}

			//$data = array();
			//$data[$tmpModelName] = array();
			//$keys = array();
			$keys[$tmpModelName] = array();
			// loop through result set and parse
			while ($row = $results->fetch(PDO::FETCH_ASSOC)) {
				// extract PK and add to key list
				array_push($keys[$tmpModelName], $row['PRI']);
				$tmpId = $row['FK'];
				unset($row['PRI']); // for internal use - only return requested fields
				unset($row['FK']); // for internal use - only return requested fields
				/*
				// add the data rows if this is a single-collection request
				if ($requestPattern == 'CIC') {
					
					array_push($data[$tmpModelName], $row);
				}
				if ($requestPattern == 'CC') {
					array_push($data[$tmpModelName], $row);
				}
				*/

				$tmpArr = array_flip($keys[$relatedModelName]);
				// create related model row if it doesn't exist
				// no, really, it should exist.
				//if (!isset($data[$relatedModelName][$tmpArr[$tmpId]])) $data[$relatedModelName][$tmpArr[$tmpId]] = array();
				//if (!isset($data[$relatedModelName][$tmpArr[$tmpId]][$targetModelName])) $data[$relatedModelName][$tmpArr[$tmpId]][$targetModelName] = array();
				
				// add target model row to project row
				array_push($data[$relatedModelName][$tmpArr[$tmpId]][$targetModelName], $row);

				foreach ($attachedModels as $attachedModelName) {
					$attachedModelLK = $keys[$attachedModelName]['LK'];
					$attachedModelRK = $keys[$attachedModelName]['RK'];
					//unset($keys[$attachedModelName]['LK']);
					//unset($keys[$attachedModelName]['RK']);

					//if (DEBUG_MODE) Message::addDebugMessage('messages', 'aLK = '.$attachedModelLK.', aRF = '.$attachedModelRK.', data = '.$row[$attachedModelLK]);

					if (!in_array($row[$attachedModelLK], $keys[$attachedModelName])) array_push($keys[$attachedModelName], $row[$attachedModelLK]);
				}



			} // while row

		} // if relatedmodel

    	//if (DEBUG_MODE) Message::addDebugMessage('querydata', $data);
    	//if (DEBUG_MODE) Message::addDebugMessage('keys', $keys);

		// if there were any tables listed in the attach query parameter, do separate queries for those
		// and attach to results
		if ( (!empty($this->globalParams['attach'])) ) {
			foreach ($attachedModels as $attachedModelName) {
				//break;
				// define a bucket for the attached table
				//$data[$attachedModelName] = array();
				// check the target model's relationship to the attached model to build query
				// if the models aren't related, don't bother and bail
				//if (empty($targetModel->relationships[$attachedModelName])) continue;

				// get the attached model records based on target model PK using IN()
				//$keys[$attachedModelName] = array();
				//$attachedModelPK = $targetModel->relationships[$attachedModelName]['remoteKey'];
				//$attachedModelFK = $targetModel->relationships[$attachedModelName]['localKey'];

				/*
				// set target data alias
				$tmpTargetData = $relatedModel === NULL ? $data[$targetModelName] : $data[$relatedModelName][$targetModelName];
				// loop through target data
				foreach ($tmpTargetData as $row) {
					if (!in_array($row[$attachedModelFK], $keys[$attachedModelName])) array_push($keys[$attachedModelName], $row[$attachedModelFK]);
				}
				*/

				//if ($relatedModel === NULL) {
				//	foreach ($data[$targetModelName] as $row) {
				//		if (!in_array($row[$attachedModelFK], $keys[$attachedModelName])) array_push($keys[$attachedModelName], $row[$attachedModelFK]);
				//	}
				//
				//} else {
					/*
					foreach ($data[$relatedModelName] as $tmpModel) {
						foreach ($tmpModel as $row) {
							//if (!in_array($row[$attachedModelFK], $keys[$attachedModelName])) array_push($keys[$attachedModelName], $row[$attachedModelFK]);

						}
					}
					*/
					//$tmpArr = array_flip($keys[$targetModelName]);
				//	foreach ($keys[$targetModelName] as $tmpIndex => $tmpId) {
				//		if (DEBUG_MODE) Message::addDebugMessage('message', 'tmpIndex = '.$tmpIndex.', tmpId = '.$tmpId.', '.$attachedModelFK.' = '.$data[$relatedModelName][$targetModelName][$tmpIndex][$attachedModelFK]);
				//		array_push($keys[$attachedModelName], $data[$relatedModelName][$targetModelName][$tmpIndex][$attachedModelFK]);
				//	}
				//}

				unset($keys[$attachedModelName]['LK']);
				unset($keys[$attachedModelName]['RK']);


    			//if (DEBUG_MODE) Message::addDebugMessage('keys', $keys);



				$attachedModel = new $attachedModelName($this->db, array($attachedModelName => array('id' => $keys[$attachedModelName], 'requestPattern' => 'CI')));
				
				// and append to bucket
				$data = array_merge($data, $attachedModel->get());

			} // foreach $attachedModelName
		} // if attached

    	//if (DEBUG_MODE) Message::addDebugMessage('fieldList', $this->getFieldList());
    	//if (DEBUG_MODE) Message::addDebugMessage('keys', $keys);
    	//if (DEBUG_MODE) Message::addDebugMessage('queries', $queryList);
    	//if (DEBUG_MODE) Message::addDebugMessage('querydata', $data);
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

