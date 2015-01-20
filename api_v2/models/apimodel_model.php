<?php

class Model {

	protected $db = null;
	protected $table = null;
	
	// the constructor function is called whenever a new instance
	// of the object is created and any parameters are passed to it
	function __construct(&$db, $table) {
		$this->db = $db;
		$this->table = $table;
	}

	public function get($options = array()) {
		if (empty($options['fields'])) $options['fields'] = '*';
		if (empty($options['id'])) $options['id'] = null;
		$query = "SELECT ".$options['fields']." FROM ".$this->table;
		if (!empty($options['id'])) $query .= " WHERE id = ". $this->db->quote($options['id']);
		$results = $this->db->query($query);
		if ($results === false) {
			return false;
		}
		return $results->fetchAll(PDO::FETCH_ASSOC);

	} // get

	public function save($id = null) {

	} // save

	public function delete($id = null) {

	} // delete


} // Model

