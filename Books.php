<?php

require_once 'Database.php';

class Books {
	/**
	 * list all records
	 * @return array
	 * @throws Exception
	 */
	public static function getAll() {
		$db = Database::getInstance();
		return $db->getAll('SELECT `name`, `genre`, `description`, `price`, `availability` FROM `books`;');
	}
	
	/**
	 * viewing a recording (by ID)
	 * @param int $id ID record
	 * @return array|bool|false
	 * @throws Exception
	 */
	public static function getById($id) {
		$db = Database::getInstance();
		if (is_numeric($id)) {
			return $db->getRow('SELECT `name`, `genre`, `description`, `price`, `availability` FROM `books` WHERE `id` = ?i;', $id);
		} else {
			return [];
		}
	}
}
