<?php

abstract class Api {
	public $apiName = '';
	protected $method = '';
	public $requestUri = [];
	public $requestParams = [];
	protected $action = '';
	
	/**
	 * constructor
	 */
	public function __construct() {
		header('Access-Control-Allow-Orgin: *');
		header('Access-Control-Allow-Methods: *');
		header('Content-Type: application/json');
		$this->requestUri = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
		$this->requestParams = $_REQUEST;
		$this->method = $_SERVER['REQUEST_METHOD'];
	}
	
	/**
	 * run
	 * @return mixed
	 */
	public function run() {
		if (array_shift($this->requestUri) !== 'api' || array_shift($this->requestUri) !== $this->apiName) {
			throw new RuntimeException('API Not Found', 404);
		}
		$this->action = $this->getAction();
		
		if (method_exists($this, $this->action)) {
			return $this->{$this->action}();
		} else {
			throw new RuntimeException('Invalid Method', 405);
		}
	}
	
	/**
	 * return response
	 * @param array|string $data data
	 * @param int $status
	 * @return false|string
	 */
	protected function response($data, $status = 500) {
		header('HTTP/1.1 '.$status.' '.$this->requestStatus($status));
		return json_encode($data);
	}
	
	/**
	 * request status
	 * @param int $code code status
	 * @return string
	 */
	private function requestStatus($code) {
		$status = [
			200 => 'OK',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			500 => 'Internal Server Error',
		];
		return ($status[$code]) ? $status[$code] : $status[500];
	}
	
	/**
	 * defining an action 
	 * @return string|null
	 */
	protected function getAction() {
		$method = $this->method;
		switch ($method) {
			case 'GET':
				if ($this->requestUri) {
					return 'viewAction';
				} else {
					return 'indexAction';
				}
				break;
			default:
				return null;
		}
	}
	
	abstract protected function indexAction();
	abstract protected function viewAction();
}
