<?php
/*****
   users.php
 **/

class Users extends Model {

	protected $tableName = 'users';

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
}