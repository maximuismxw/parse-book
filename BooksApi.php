<?php

require_once 'Api.php';
require_once 'Books.php';

class BooksApi extends Api {
	public $apiName = 'books';
	
	/**
	 * list all records
	 * @return false|string
	 * @throws Exception
	 */
	public function indexAction() {
		$records = Books::getAll();
		if ($records) {
			return $this->response($records, 200);
		}
		return $this->response('Data not found', 404);
	}
	
	/**
	 * viewing a recording (by ID)
	 * @return false|string
	 * @throws Exception
	 */
	public function viewAction() {
		$id = array_shift($this->requestUri);
		if ($id) {
			$record = Books::getById($id);
			if ($record) {
				return $this->response($record, 200);
			}
		}
		return $this->response('Data not found', 404);
	}
}
