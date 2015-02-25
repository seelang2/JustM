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
  Defines the base methods and scaffold functionality
 **/
class Model {

	protected $modelName = NULL;						// model (class) name
  protected $db = NULL;								// pointer to database connection
  protected $tableName = NULL;						// table name in db
  protected $primaryKey = NULL;						// PK field
  protected $fieldList = array();						// list of table fields
  
  protected $parentModel = NULL;						// pointer to model this model is bound to
  protected $boundModels = array();					// list of models bound to this model

  /****
    The options array sets the default options used by the model
    Valid options:
    	alias 											Aliased model name (used in relationships)
      forceUnthreaded             Flag to override threaded data join
   **/
  protected $options = array();						// option settings for model
  
  protected $eventQueue = array(						// container for event queues
  	'beforeFind',									// triggered on find() before processing
  	'afterFind', 									// triggered on find() after processing
  	'beforeUpdate',
  	'afterUpdate'
  );
  
  /****
    Models are related together by defining the relationship in an array in the called model.
    The relationships get defined using the following structure:

    A has B - PK in A, FK in B
    A belongsTo B - PK in B, FK in A
    A HABTM B - FK in link table

    protected $relationships = array(
      'alias' => array(                    // Alias to use for this table (use model name if no alias)
          'model'     => 'modelClass',     // Name of the model (or model on other side of link table)
          'type'      => 'relType',        // Type of relationship - has, belongsTo, HABTM
          'localKey'  => 'KeyField',       // Local model PK ()
          'remoteKey' => 'KeyField'        // PK or FK field name in related model (or local model's FK in link table)
          'linkTable' => 'linkTableName'   // Name of link table to use
      )
    );

   **/
  protected $relationships = array();					// array of table relationships

  protected $data = NULL;								// data being manipulated

  /****
    a reference to the database connection object is passed to the constructor and stored locally
    as dependency injection, as are the request parameters.

    Takes the parsed request parameters and instantiates the models required by the request. 
    Request parameters are stored in each model in the chain. These options can be changed or 
    overridden by method calls.
   **/
  function __construct(&$db, $options = array(), &$parentModel = NULL) {
    $this->db = $db;
    $this->modelName = strtolower(get_class($this));
    $this->parentModel = $parentModel;
    $this->options = $options;

    if (DEBUG_MODE) Message::addDebugMessage('modelOptions', $options);

    // load field list from cache file
    if (empty($this->fieldList)) {
      $this->loadFieldList();
    }

    // find and set primary key
    foreach($this->fieldList as $field) {
      if ($field['Key'] == 'PRI') {
          $this->primaryKey = $field['Field'];
          break;
      }
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
    Returns model name or alias if set
   **/
  public function getModelName() {
    return empty($this->options['alias']) ? $this->modelName : $this->options['alias'];
  } // getModelName

  /****
    Finds the root of a chain of connected models
   **/
  public function getRootModel() {
    $rootModel = $this;
    while ($rootModel->parentModel != NULL) {
        $rootModel = $rootModel->parentModel;
    }

    return $rootModel;
  } // getRootModel

  /****
    Finds a model in a chain of connected models
   **/
  public function getModel($modelName) {

  } // getModel

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
    Returns data variable value
   **/
  public function getData() {

    return $this->Data;

  } // getData

  /****
    Sets model options
   **/
  public function setOptions($options) {

    $this->options = array_merge($this->options, $options);

  } // setOptions

  /****
    Get a single option parameter or NULL if it doesn't exist
    (Options shouldn't have a NULL value; they should instead simply not exist)
   **/
  public function getOption($option) {

    if (!isset($this->options[$option])) return NULL;
    return $this->options[$option];

  } // getOption

  /****
    Sanitize data for use in SQL query
   **/
  public function sanitize($data = NULL) {
    if (empty($data)) return false;
    return $this->db->quote($data);
  } // sanitize

  /****
    Binds a model to the current model and returns a reference to the new model
   **/
  public function bind($modelName, $options = array()) {
  	// allow an alias to be passed as the name of the model
  	$alias = empty($options['alias']) ? $modelName : $options['alias'];
  	// create new model instance and attach as new public member
  	$this->{$alias} = new $modelName($this->db, $options, $this);
  	// add model to list of bound models
  	$this->boundModels[$alias] = &$this->{$alias};
    return $this->{$alias};
  } // bind

  /****
    Registers an event listener to a queue 
    The callback will be passed two parameters as defined below: 
    	function callback(mixed $data, array $options)
   **/
  public function register($eventName, $callback, $options = array()) {
  	if (!isset($this->eventQueue[$eventName])) $this->eventQueue[$eventName] = array();
  	array_push($this->eventQueue[$eventName], array($callback, $options));
  } // register

  /****
    Executes the callbacks in an event queue, optionally passing data to each
   **/
  public function trigger($eventName, $data = NULL) {
  	if (!isset($this->eventQueue[$eventName])) return false;
  	foreach ($this->eventQueue[$eventName] as $callbackArray) {
  		call_user_func($callbackArray[0], $data, $callbackArray[1]);
  	}
  } // trigger

  /****
    Retrieves resource data.
    Options:
    	type              What kind of search to perform: 'one','all' ,'count', 'threaded'
    	id                PK to return. May be single value or array of values
  		fields            Array of fields to return. Returns all fields by default
  		limit             Array containing limit parameters - [0] = offset, [1] = range
  		relatedId         FK from related (bound) model. May be single value or array of values
  		relatedFields     Fields in bound models to return
  
    Returns an array containing the following:
      resultSet         The fields specified (or all fields) to be retrieved
      PKList            Array of PKs in result set
      status            Result code of operation. Directly maps to HTTP status codes
      message           Additional information regarding status
  
   **/
  public function find($options = array()) {
  	$this->trigger('beforeFind');

    $thisModelName = $this->getModelName(); // quit looking up the model name all the time

    // override default options with passed-in options
    $options = array_merge($this->options, $options);

    if (DEBUG_MODE) Message::addDebugMessage('findOptions', $options);

    // initialize data structure
    $this->data = array(
      'status'      => NULL,
      'PKList'      => array(),
      'resultSet'   => array($thisModelName => array())
    );

    $useFKList = false; // set initial value to false

    if (empty($options['type'])) $options['type'] = 'threaded';
  	$fields = array();

    // if this is a bound model OR if there are models bound to this one, we need to check out
    // the relationship between the models
    if (!empty($this->boundModels)) {
      // check to see if any of the bound models require the parent's FK field
      // (a 'has' relationship in the bound model)
      foreach ($this->boundModels as $tmpModelName => $tmpModelPointer) {
        $relationshipType = $tmpModelPointer->relationships[$thisModelName]['type'];
        if (DEBUG_MODE) Message::addDebugMessage('messages', $tmpModelName.' bound to '.$thisModelName.' using '.$relationshipType);
        if ($relationshipType == 'has') {
          // create a bucket to hold the FKs for this bound model
          if (!isset($this->data['FKList'])) $this->data['FKList'] = array();
          $this->data['FKList'][$tmpModelName] = array();

          // in 'A has B' the FK is in B
          //$FKey = $tmpModelPointer->relationships[$thisModelName]['localKey'];
          array_unshift($fields, $this->tableName.'.'.$tmpModelPointer->relationships[$thisModelName]['remoteKey']." AS '".$tmpModelName.".FK'");
        }
      }
    }

    //if ($this->parentModel !== NULL) $useFKList = true;

    // if (DEBUG_MODE) Message::addDebugMessage($this->getModelName().'boundModels', $this->boundModels);

    // if this is a bound model, get the relationship info about the parent
    if ($this->parentModel !== NULL) {
      $parentModelName = $this->parentModel->getModelName();

      if (DEBUG_MODE) Message::addDebugMessage('messages', 'parentModelName = '.$parentModelName);

      // check what kind of relationship this is and proceed accordingly
      if (DEBUG_MODE) Message::addDebugMessage('messages', $thisModelName.' '.$this->relationships[$parentModelName]['type'] .' '.$parentModelName);

      if ($this->relationships[$parentModelName]['type'] == 'has') {
        // A has B - PK in A, FK in B
        $key = $this->relationships[$parentModelName]['localKey'];

        
      }
      if ($this->relationships[$parentModelName]['type'] == 'belongsTo') {
        // A belongsTo B - PK in B, FK in A
        // create a bucket to hold the FKs for this bound model
        if (!isset($this->data['FKList'])) $this->data['FKList'] = array();
        $this->data['FKList'][$parentModelName] = array();
        $key = $this->relationships[$parentModelName]['localKey'];
      }
      if ($this->relationships[$parentModelName]['type'] == 'HABTM') {
        // A HABTM B - FK in link table
      }

      /*
      $this->data['FKList'] = array();
      $parentModelName = $this->parentModel->getModelName();

      if (!empty($this->relationships[$parentModelName]['linkTable'])) {
        $relatedTable = $this->relationships[$parentModelName]['linkTable'];
      }

      $localKey = $this->relationships[$parentModelName]['localKey']; // PARENT model PK
      $remoteKey = $this->relationships[$parentModelName]['remoteKey']; // PARENT model FK in bound model
      */
    
      if (isset($this->data['FKList'][$parentModelName])) array_unshift($fields, $this->tableName.'.'.$key." AS '".$parentModelName.".FK'");
    } // if parentModel

  	// add in the PK and FK fields for the models for aggregation purposes
  	array_unshift($fields, $this->tableName.'.'.$this->primaryKey.' AS PRI');

    // if type is count then just perform a count query with one field
    if (strtolower($options['type']) == 'count') {
      array_push($fields, "COUNT(*) AS count");
  	} else {
      // if the fields have been passed use them 
      if (!empty($options['fields'])) {
        foreach ($options['fields'] as $tmpField) {
          array_push($fields, "`".$this->tableName."`".'.'."`".$tmpField."`");
        }
  		} else {
  			// ...otherwise get all fields
  			foreach ($this->getFieldList() as $field) {
  				array_push($fields, "`".$this->tableName."`".'.'."`".$field['Field']."`");
  			}
  		}
  	}

  	// check for limit query parameter and validate before using
  	// there may be a faster way to do this
  	if (!empty($options['limit'])) {
  		//$limit = explode(',',$options['limit']);
      $limit = $options['limit'];
  		// validate each limit parameter
  		foreach($limit as $tmpParam) {
  			if (!is_numeric($tmpParam)) {
  				$limit = NULL; // just invalidate the entire limit param
  				break; // terminate validation
  			}
  		}
  	}

  	// begin building query
    $query = 'SELECT DISTINCT '. join(',',$fields) . ' FROM `'. $this->tableName .'`';

    // add id if passed
    if (isset($options['id'])) {
      $id = $options['id'];
      if (!is_array($id)) $id = $this->sanitize($id);
      
      if (is_array($id)) {
        $id = array_keys(array_flip($id)); // make id array unique
        $query .= " WHERE {$this->primaryKey} IN(".join(',', $id).")";
      } else {
        $query .= " WHERE {$this->primaryKey} = {$id}";
      }      
    }

    if ($this->parentModel !== NULL) {
      // add id if passed
      if (isset($options['relatedId'])) {
        $id = $options['relatedId'];
        if (!is_array($id)) $id = $this->sanitize($id);
        
        if (is_array($id)) {
          $id = array_keys(array_flip($id)); // make id array unique
          $query .= " WHERE {$key} IN(".join(',', $id).")";
        } else {
          $query .= " WHERE {$key} = {$id}";
        }      
      }
    }

    // apply limit
    if (!empty($limit)) { 
      $query .= ' LIMIT '.join(',', $limit);
    }

    if (DEBUG_MODE) Message::addDebugMessage('queries', $query); // debug messages are now serialized

    // query built, now send to server
    $results = $this->db->query($query);

    // if there's a query error terminate with error status
    if ($results == false) {
      return array(
        'status'      => 400,
        'message'     => 'Query error. Please check request parameters.'
      );
    }

    // if the result set is empty terminate with error status
    if ($this->db->query("SELECT FOUND_ROWS()")->fetchColumn() == 0) {
      return array(
        'status'      => 404,
        'message'     => 'No results matching your request were found.'
      );
    }

    // make short alias to resultSet
    $resultSet = &$this->data['resultSet'][$thisModelName];

    // loop through result set and parse
    while ($row = $results->fetch(PDO::FETCH_ASSOC)) {
      // extract PK and add to key list
      array_push($this->data['PKList'], $row['PRI']);
      unset($row['PRI']); // for internal use - only return requested fields
      
      // go through FKList 
      if (isset($this->data['FKList'])) {
        foreach ($this->data['FKList'] as $tmpModelName => $tmpArray) {
          array_push($this->data['FKList'][$tmpModelName], $row[$tmpModelName.'.FK']);
          unset($row[$tmpModelName.'.FK']); // for internal use - only return requested fields        
        }
      }

      array_push($resultSet, $row);

    } // while row

    // if there are models bound to this one, get their data as well
    if (DEBUG_MODE) Message::addDebugMessage($thisModelName.'boundModels', $this->boundModels);

    foreach ($this->boundModels as $tmpModelName => $tmpModelPointer) {
      $relationshipType = $tmpModelPointer->relationships[$thisModelName]['type'];
      
      if (DEBUG_MODE) Message::addDebugMessage('messages', $thisModelName.' now processing '.$tmpModelName.' using '.$relationshipType);

      $keyList = $relationshipType == 'has' ? $this->data['FKList'][$tmpModelName] : $this->data['PKList'];

      $tmpData = $this->{$tmpModelName}->get(array(
        'relatedId'    => $keyList
      ));
      
      if (DEBUG_MODE) Message::addDebugMessage($tmpModelName.'_getData', $tmpData);

      // now collate the data together

      if ($tmpModelPointer->getOption('forceUnthreaded') == true) {
        // leave data unthreaded
        $this->data['resultSet'] = array_merge($this->data['resultSet'], $tmpData['resultSet']);

        // remove already processed data set
        unset($tmpData['resultSet'][$tmpModelName]); 

      } else if ($options['type'] == 'threaded') {
        // thread the bound model with the parent model data
        
        foreach($this->data['resultSet'][$thisModelName] as $tmpIndex => $row) {
          //$row[$tmpModelName] = array();
          $this->data['resultSet'][$thisModelName][$tmpIndex][$tmpModelName] = array();
        }

        if ($relationshipType == 'has') {
          $tmpKeyList = $tmpData['PKList'];

        } 
        if ($relationshipType == 'belongsTo') {
          //$tmpArray = array_flip($this->data['PKList']); // invert PK list to search PKs in data
          //$tmpKeyList = $tmpData['FKList'][$tmpModelName];

          $tmpArray = array_flip($this->data['PKList']); // invert PK list to search PKs in data
          //$tmpArray = array_flip($keyList);

          foreach ($tmpData['FKList'] as $tmpParentModelName => $tmpParentFKList) {
            foreach ($tmpParentFKList as $tmpIndex => $tmpFK) {
              //if (!isset($this->data['resultSet'][$thisModelName][$tmpArray[$tmpFK]][$tmpModelName]))
              //    $this->data['resultSet'][$thisModelName][$tmpArray[$tmpFK]][$tmpModelName] = array();

              if (DEBUG_MODE) Message::addDebugMessage('messages', 'Adding '.$tmpModelName.' element '.$tmpIndex.' to '.$thisModelName.' element '.$tmpArray[$tmpFK]);
              //if (DEBUG_MODE) Message::addDebugMessage($tmpModelName.' element '.$tmpIndex, $tmpData['resultSet'][$tmpModelName][$tmpIndex]);
              //if (DEBUG_MODE) Message::addDebugMessage($thisModelName.' element '.$tmpArray[$tmpFK], $this->data['resultSet'][$thisModelName][$tmpArray[$tmpFK]][$tmpModelName]);

              array_push(
                $this->data['resultSet'][$thisModelName][$tmpArray[$tmpFK]][$tmpModelName],
                $tmpData['resultSet'][$tmpModelName][$tmpIndex]
              );
            }
          }

        } 
        if ($relationshipType == 'HABTM') {

        } 

        /*
        //$tmpArray = array_flip($this->data['PKList']); // invert PK list to search PKs in data
         $tmpArray = array_flip($keyList);

        foreach ($tmpKeyList as $tmpParentModelName => $tmpParentFKList) {
          foreach ($tmpParentFKList as $tmpIndex => $tmpFK) {
            //if (!isset($this->data['resultSet'][$thisModelName][$tmpArray[$tmpFK]][$tmpModelName]))
            //    $this->data['resultSet'][$thisModelName][$tmpArray[$tmpFK]][$tmpModelName] = array();
            
            array_push(
              $this->data['resultSet'][$thisModelName][$tmpArray[$tmpFK]][$tmpModelName],
              $tmpData['resultSet'][$tmpModelName][$tmpIndex]
            );
          }
        }
        */

        // now passthrough any other attached data in resultset
        // remove already processed data set
        unset($tmpData['resultSet'][$tmpModelName]); 
        // now merge remaining data
        $this->data['resultSet'] = array_merge($this->data['resultSet'], $tmpData['resultSet']);

      } else {
        // leave data unthreaded
        //$this->data['resultSet'][$tmpModelName] = $tmpData['resultSet'][$tmpModelName];
        $this->data['resultSet'] = array_merge($this->data['resultSet'], $tmpData['resultSet']);
        
        // remove already processed data set
        unset($tmpData['resultSet'][$tmpModelName]); 
      }



    } // foreach boundModel


    // set Ok status
    $this->data['status'] = 200;

    // the 'afterFind' event provides a hook to manipulate the data before return
  	$this->trigger('afterFind', $this->data);

  	return $this->data;
  } // find




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
  public function get($options = array()) {
    return $this->find($options);
  } // get

  /****
    HEAD - Retrieve either a resource collection or an individual resource if a key is given
    without the representation data (schema and headers/links only)

    Status Codes 		Condition
    200 OK 			Successful request

   **/
  public function head($options = array()) {

  	return array((int)microtime(true), 'HEAD Method called.');
  } // head

  /****
    POST - Create a new resource in a given collection with complete data
   **/
  public function post($options = array()) {

  	return array((int)microtime(true), 'POST Method called.');
  } // post

  /****
    PUT - Update (replace) a resource in a collection with a given id with complete data
   **/
  public function put($options = array()) {

  	return array((int)microtime(true), 'PUT Method called.');
  } // put

  /****
    PATCH - Update a resource in a collection with a given id with partial data
   **/
  public function patch($options = array()) {

  	return array((int)microtime(true), 'PATCH Method called.');
  } // patch

  /****
    DELETE - Delete a resource in a collection with a given id
   **/
  public function delete($options = array()) {

  	return array((int)microtime(true), 'DELETE Method called.');
  } // delete

 

} // class Model

