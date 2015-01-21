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

    protected $belongsTo = array(
        'roles' => array( 					// Alias to use for this table (use model name if no alias)
            'model'     => 'Roles', 		// Name of the model (or model on other side of link table)
            'fk'        => 'role_id', 		// Foreign key field name for local model
            'remoteFK'  => 'id' 			// The foerign key field name for the other model
            //'linkTable' => 'linkTableName'      // Name of link table to use
        )
    );

/*
	public $fields = array(
		'id',
		'project_id',
		'role_id',
		'time_start',
		'time_stop',
		'duration'
	);

	protected $relationships = array(
		'roles' => 'Roles.id = Timeslips.role_id'
//		'roles' => array(
//			'belongsTo' => 'Roles.id = Timeslips.role_id'
//		)
	);
*/

}