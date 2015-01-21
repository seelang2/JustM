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

    protected $parentModel = NULL;
    protected $fieldList = array();
    protected $primaryKey = NULL;
    protected $db = NULL;
    protected $relatedModels = array(); // maintain list of attached models to avoid loops

    /****
      The following properties are used by the models that inherit from Model
     **/
    
    public $tableName = NULL;                       // table name in db

    // define relationships using arrays
    protected $has = array();                       // one to one or one to many - FK is in other table
    protected $belongsTo = array();                 // many to one - FK is in this table
    protected $hasAndBelongsToMany = array();       // many to many - uses a link table

    /****
        The relationships get defined using the following structure:

        $relation = array(
            'alias' => array(                       // Alias to use for this table (use model name if no alias)
                'model'     => 'modelClass',        // Name of the model (or model on other side of link table)
                'fk'        => 'foreignKeyField',   // Foreign key field name for local model
                'remoteFK'  => 'remoteForeignKey'   // The foerign key field name for the other model
                'linkTable' => 'linkTableName'      // Name of link table to use
            )
        );
     **/

    /****
      a reference to the database connection object is passed to the constructor and stored locally
      as dependency injection

      This needs to be revised to take the parsed request parameters and only instantiate the models
      required by the request. Additionally, it should set the ID and options on the models for the 
      ensuing query operation. These options can be changed or overridden by method calls.
     **/
    function __construct(&$db, &$parentModel = NULL) {
        $this->parentModel = $parentModel;
        $this->db = $db;

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
        
        // get root model
        $rootModel = $this->getRootModel();
        $thisModel = get_class($this);

        // the root instance has to add itself to the list of related models as well
        if (empty($rootModel->relatedModels)) $rootModel->relatedModels[$thisModel] = &$this;

        // connect all related models to this one
        // attach the related model using its alias
        if (!empty($this->has)) {
            foreach ($this->has as $alias => $relModel) {
                $model = $relModel['model'];

                // check if this model is already in the chain
                if (!array_key_exists($model, $rootModel->relatedModels)) {
                    // model is not in chain, create new instance
//                    echo "<p>Attaching $model to ".get_class($this)." as $alias (root is ".get_class($rootModel).")</p>";
                    $rootModel->relatedModels[$model] = new $model($this->db, $this);
                    // then add to chain as reference to alias
                    $this->{$alias} = &$rootModel->relatedModels[$model];
                } else {
                    // model is already attached, so just create alias link
                    //$this->{$alias} = &$rootModel->relatedModels[$model];
                }

            } // foreach
        } // if has

        if (!empty($this->belongsTo)) {
            foreach ($this->belongsTo as $alias => $relModel) {
                $model = $relModel['model'];

                // check if this model is already in the chain
                if (!array_key_exists($model, $rootModel->relatedModels)) {
                    // model is not in chain, create new instance
//                    echo "<p>Attaching $model to ".get_class($this)." as $alias</p>";
                    $rootModel->relatedModels[$model] = new $model($this->db, $this);
                    // then add to chain as reference to alias
                    $this->{$alias} = &$rootModel->relatedModels[$model];
                } else {
                    // model is already attached, so just create alias link
                    //$this->{$alias} = &$rootModel->relatedModels[$model];
                }

            } // foreach
        } // if belongsTo

        if (!empty($this->hasAndBelongsToMany)) {
            foreach ($this->hasAndBelongsToMany as $alias => $relModel) {
                $model = $relModel['model'];

                // check if this model is already in the chain
                if (!array_key_exists($model, $rootModel->relatedModels)) {
                    // model is not in chain, create new instance
//                    echo "<p>Attaching $model to ".get_class($this)." as $alias</p>";
                    $rootModel->relatedModels[$model] = new $model($this->db, $this);
                    // then add to chain as reference to alias
                    $this->{$alias} = &$rootModel->relatedModels[$model];
                } else {
                    // model is already attached, so just create alias link
                    //$this->{$alias} = &$rootModel->relatedModels[$model];
                }

            } // foreach 
        } // if HABTM


    } // __construct

    public function test($params = NULL) {
        echo '<p>Successfull model test.</p>';
        echo '<pre>'.print_r($params,true).'</pre>';

    } // test

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
      Returns array of models attached to this model
     **/
    public function getRelatedModels() {
        $rootModel = $this->getRootModel();
        $modelList = array();
        foreach($rootModel->relatedModels as $modelName => $modelObj) {
            $modelList[] = $modelName;
        }
        return $modelList;
    }

    /****
      Returns a specified model from the array of attached models
     **/
    protected function getModel($modelName) {
        $root = $this->getRootModel();

        return $root->relatedModels[$modelName];
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
        if (file_exists(CACHE_DIR.DS.get_class($this).'.dat')) {
            $this->fieldList = loadData(CACHE_DIR.DS.get_class($this).'.dat');
            return;
        }

        $results = $this->db->query('DESCRIBE '.$this->tableName);
        $this->fieldList = $results->fetchAll(PDO::FETCH_ASSOC);
        saveData($this->fieldList, CACHE_DIR.DS.get_class($this).'.dat');

    } // loadFieldList

    /****
      Returns dump of fieldList
     **/
    public function getFieldList() {
        if (empty($this->fieldList)) return false;

        return $this->fieldList;

    } // getFieldList

    /****
      Dynamically builds query
      Not currently used but kept for reference
     **/
    protected function buildQuery(&$parentModel, &$query, $parentAlias = NULL) {
        if (empty($parentAlias)) $parentAlias = $parentModel->tableName;

        if (!empty($parentModel->has)) {
            foreach ($parentModel->has as $alias => $relModel) {
                $model = $relModel['model'];
                // create instance of related model and attach to current model
                $parentModel->{$model} = new $model($parentModel->db); 

                $query .= " LEFT JOIN ".$parentModel->{$model}->tableName.
                          " AS ".$alias." ON ".$parentAlias.".".$parentModel->primaryKey." = ".$alias.".".$relModel['fk'];

                $this->{$model}->connectModels($parentModel->{$model}, $query, $alias);        
            }
        }
    }

    /****
      Recursively queries and builds result dataset
     **/
    protected function getRelatedData($parentModel, $id = NULL, $key = 'id', $parentAlias = NULL, $linkTable = NULL, $remoteFK = NULL) {
        if (empty($parentAlias)) $parentAlias = get_class($parentModel);

//echo "<p>Running getRelatedData on ".get_class($parentModel)." as $parentAlias through $linkTable with key $key = $id and RFK $remoteFK</p>";

        // first, get the record specified by $key = $id
        $query = "SELECT * FROM ". $parentModel->tableName ." AS ". $parentAlias;
        if (!empty($linkTable)) $query .= ", $linkTable";
        if (!empty($id)) $query .= " WHERE ".$key." = '". $id ."' ";
        if (!empty($linkTable)) $query .= " AND {$parentModel->primaryKey} = $remoteFK";

//echo "<p>$query</p>";

        $result = $parentModel->db->query($query);
        if (!$result) throw new Exception("SQL query error. Query: $query");

        $tmp = $result->fetchAll(PDO::FETCH_ASSOC);

//echo pr($tmp);

        // for each result row:
            // if there are any connected models, for each model:
                // get the records that relate to the row
        foreach ($tmp as $rowKey => $row) {
            // get the $row's id
            $rowID = $row[$parentModel->primaryKey];

            if (!empty($parentModel->has)) {
                foreach ($parentModel->has as $alias => $relModel) {
                    $modelName = $relModel['model'];
                    $model = $parentModel->getModel($modelName);

                    // this is a 1-M, so the FK is in the related table, so get the FK name
                    $fk = $relModel['fk'];

                    $tmp[$rowKey][$alias] = $model->getRelatedData(
                        $model,
                        $rowID,
                        $alias.".".$fk,
                        $alias
                    );
               }
            } // if has

            if (!empty($parentModel->belongsTo)) {
                foreach ($parentModel->belongsTo as $alias => $relModel) {
                    $modelName = $relModel['model'];
                    $model = $parentModel->getModel($modelName);

                    // this is a M-1, so the FK is in this table, so get the FK name
                    $fk = $relModel['fk'];
                    // now get the related row ID from the row's FK
                    $fkID = $row[$fk];

                    $tmp[$rowKey][$alias] = $model->getRelatedData(
                        $model,
                        $fkID,
                        $alias.".".$model->primaryKey,
                        $alias
                    );
               }
            } // if belongsTo

            if (!empty($parentModel->hasAndBelongsToMany)) {
                foreach ($parentModel->hasAndBelongsToMany as $alias => $relModel) {
                    $modelName = $relModel['model'];
                    $model = $parentModel->getModel($modelName);

                    // this is a 1-M<->M-1, so the FK is in the link table, so get the FK name
                    $fk = $relModel['fk'];

                    $tmp[$rowKey][$alias] = $model->getRelatedData(
                        $model,
                        $rowID,
                        $relModel['linkTable'].".".$fk,
                        $alias,
                        $relModel['linkTable'],
                        $relModel['linkTable'].".".$relModel['remoteFK']
                    );
               }
            } // if hasAndBelongsToMany


        } // foreach $tmp

        return $tmp;

    } // getRelatedData

    /****
      -
     **/
    public function getAll($params = NULL) {
        
        $data[get_class($this)] = $this->getRelatedData($this);

        return $data;

    } // getAll

    /****
      -
     **/
    public function getOne($params = NULL) {

        if (count($params) != 1) {
            throw new Exception("Incorrect number of parameters for ".get_class($this)."->getOne");
        }

        $data[get_class($this)] = $this->getRelatedData($this, $params[0]);

        return $data;

    } // getOne

    /****
      -
     **/
    public function query($params = NULL) {

    } // query

    /****
      -
     **/
    public function save($params = NULL) {

    } // save

    /****
      -
     **/
    public function count($params = NULL) {

    } // count


} // class Model



// the original model base class
class Model_old {
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

