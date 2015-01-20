<?php
/*****
   projects.php
 **/

class Projects extends Model {

	public $tableName = 'projects';

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
}