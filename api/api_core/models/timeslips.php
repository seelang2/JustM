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
	        'roles' => array( 					// Alias to use for this table (use model name if no alias)
	            'model'     => 'Roles', 		// Name of the model (or model on other side of link table)
	            'localKey'  => 'role_id', 		// Foreign key field name for local model
	            'remoteKey' => 'id' 			// The foerign key field name for the other model
	            //'linkTable' => 'linkTableName'      // Name of link table to use
	        )
	    //)
    );


}