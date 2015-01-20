<?php
/*****
   timeslips.php
 **/

class Timeslips extends Model {

	public $tableName = 'timeslips';

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
}