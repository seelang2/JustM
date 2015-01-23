<?php
/*********
 * users.php - Model file
 * @author: Chris Langtiw
 *
 * @description: 
 * 
 ****/


class Users extends Model {

	public $tableName = 'users';

    protected $relationships = array(
    	//'relation' => array(
	        'alias' => array(                       // Alias to use for this table (use model name if no alias)
	            'model'     => 'modelClass',        // Name of the model (or model on other side of link table)
	            'localKey'  => 'foreignKeyField',   // Foreign key field name for local model
	            'remoteKey' => 'remoteForeignKey'   // The foerign key field name for the other model
	            'linkTable' => 'linkTableName'      // Name of link table to use
	        )
        //)
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