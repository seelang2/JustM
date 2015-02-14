<?php
/*********
 * timeslips.php - Model file
 * @author: Chris Langtiw
 *
 * @description: 
 * 
 ****/


class Timeslips extends Model {

	public $tableName = 'timeslips';

    protected $relationships = array(
	    //'belongsTo' => array(
	        'projects' => array( 					// Alias to use for this table (use model name if no alias)
	            'model'     => 'Projects', 			// Name of the model (or model on other side of link table)
	            'localKey'  => 'project_id', 		// PK/FK field name for local model
	            'remoteKey' => 'id' 				// PK/FK field name for the other model
	            //'linkTable' => 'linkTableName' 	// Name of link table to use
	        ),
	        'roles' => array( 						// Alias to use for this table (use model name if no alias)
	            'model'     => 'Roles', 			// Name of the model (or model on other side of link table)
	            'localKey'  => 'role_id', 			// Foreign key field name for local model
	            'remoteKey' => 'id' 				// The foerign key field name for the other model
	            //'linkTable' => 'linkTableName' 	// Name of link table to use
	        )
	    //)
    );


}