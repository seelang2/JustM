<?php
/*****
   roles.php
 **/

class Roles extends Model {

	protected $tableName = 'roles';

	protected $fields = array(
		'id',
		'name',
		'rate',
		'description',
		'color'
	);

}