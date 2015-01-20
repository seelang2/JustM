<?php
/*****
   projects.php
 **/

class Projects extends Model {

	public $tableName = 'projects';

    $has = array(
        'timeslips' => array( 					// Alias to use for this table (use model name if no alias)
            'model'     => 'Timeslips', 		// Name of the model (or model on other side of link table)
            'fk'        => 'id', 				// Foreign key field name for local model
            'remoteFK'  => 'project_id' 		// The foreign key field name for the other model
            //'linkTable' => 'linkTableName' 	// Name of link table to use
        )
    );


/*
	protected $fields = array(
		'id',
		'user_id',
		'name',
		'description'
	);

	protected $relationships = array(
		'timeslips' => 'Projects.id = Timeslips.project_id'
//		'timeslips' => array(
//			'hasMany' => 'Projects.id = Timeslips.project_id'
//		)
	);
*/

}