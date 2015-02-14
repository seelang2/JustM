<?php
/*********
 * roles.php - Model file
 * @author: Chris Langtiw
 *
 * @description: 
 * 
 ****/


class Tags extends Model {

	public $tableName = 'tags';

    protected $relationships = array( 
	    //'habtm' => array(
	        'projects' => array( 				// Alias to use for this table (use model name if no alias)
	            'model'     => 'Projects', 		// Name of the model (or model on other side of link table)
	            'localKey'  => 'id', 			// PK/FK field name for local model
	            'remoteKey' => 'tag_id', 		// PK/FK field name for the other model (or matching FK in link table)
	            'linkTable' => 'projectstags' 	// Name of link table to use
	        )
	    //)
    );

}