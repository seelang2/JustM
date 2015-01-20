<?php
/*****
   users.php
 **/

class Users extends Model {

	public $tableName = 'users';

    $relation = array(
        'alias' => array(                       // Alias to use for this table (use model name if no alias)
            'model'     => 'modelClass',        // Name of the model (or model on other side of link table)
            'fk'        => 'foreignKeyField',   // Foreign key field name for local model
            'remoteFK'  => 'remoteForeignKey'   // The foerign key field name for the other model
            'linkTable' => 'linkTableName'      // Name of link table to use
        )
    );

/*
	protected $fields = array(
		'id',
		'firstname',
		'lastname',
		'email',
		'password',
		'authtoken',
		'status'
	);

	protected $relationships = array(
		'projects' => 'Users.id = Projects.user_id'
//		'projects' => array(
//			'hasMany' => 'Users.id = Projects.user_id'
//		)
	);
*/


}