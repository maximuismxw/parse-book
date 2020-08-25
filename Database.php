<?php

class Database {
	private static $instance;
	private $pdo;
	private $stats;
	private $config = [
		'dsn' => '',
		'driver' => 'mysql',
		'host' => 'localhost',
		'user' => 'root',
		'pass' => '',
		'base' => '',
		'port' => null,
		'charset' => 'utf8',
		'prefix' => 'warp'
	];
	public static $prefix = '';
	
	/**
	 * Database constructor.
	 * @throws Exception
	 */
	protected function __construct() {
		$config = include __DIR__.'/config_db.php';
		$config = array_merge($this->config, $config);
		if (!mb_strlen($config['dsn'])) {
			switch ($config['driver']) {
				case 'mysql':
					$config['dsn'] = $config['driver'].':host='.$config['host'].';dbname='.$config['base'];
					
					if (!is_null($config['port'])) {
						$config['dsn'] .= ';port='.$config['port'];
					}
					
					if ((string) mb_strlen($config['charset'])) {
						$config['dsn'] .= ';charset='.$config['charset'];
					}
					break;
			}
		}
		self::$prefix = $config['prefix'];
		
		$option = [
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
		];
		
		try {
			$this->pdo = new PDO($config['dsn'], $config['user'], $config['pass'], $option);
		} catch (PDOException $e) {
			throw new Exception($e);
		}
	}
	
	/**
	 * singleton
	 * @return Database
	 */
	public static function getInstance() {
		if (empty(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * request to DB
	 * @return mixed
	 * @throws Exception
	 */
	public function query() {
		return $this->rawQuery($this->prepareQuery(func_get_args()));
	}
	
	/**
	 * Получение строки результата (для работы с query())
	 *
	 * @param PDOStatement $result
	 * @param int $mode параметр
	 * @return mixed
	 */
	public function fetch(PDOStatement $result, $mode = PDO::FETCH_ASSOC) {
		return $result->fetch($mode);
	}
	
	/**
	 * ID последней добавленной записи
	 */
	public function insertID() {
		return $this->pdo->lastInsertId();
	}
	
	/**
	 * Получение количества возвращаемых строк (только для query())
	 *
	 * @param PDOStatement $result
	 * @return int
	 */
	public function numRows(PDOStatement $result) {
		return $result->rowCount();
	}
	
	/**
	 * Получение 1-го элемента 1-й строки результата запроса
	 *
	 * @return string|false
	 * @throws Exception
	 */
	public function getOne() {
		$query = $this->prepareQuery(func_get_args());
		if ($res = $this->rawQuery($query)) {
			$row = $this->fetch($res);
			if (is_array($row)) {
				return reset($row);
			}
		}
		return false;
	}
	
	/**
	 * Получение 1-й строки результата запроса
	 *
	 * @return array|false
	 * @throws Exception
	 */
	public function getRow() {
		$query = $this->prepareQuery(func_get_args());
		if ($res = $this->rawQuery($query)) {
			$ret = $this->fetch($res);
			return $ret;
		}
		return false;
	}
	
	/**
	 * Получение 1-й колонки результата запроса
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getCol() {
		$ret = [];
		$query = $this->prepareQuery(func_get_args());
		if ($res = $this->rawQuery($query)) {
			while ($row = $this->fetch($res)) {
				$ret[] = reset($row);
			}
		}
		return $ret;
	}
	
	/**
	 * Получение 2-х мерного массива - результата запроса
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getAll() {
		$ret = [];
		$query = $this->prepareQuery(func_get_args());
		if ($res = $this->rawQuery($query)) {
			while ($row = $this->fetch($res)) {
				$ret[] = $row;
			}
		}
		return $ret;
	}
	
	/**
	 * Получение 2-х мерного массива (индексы проставляются из поля указанного в 1-м параметре), - результата запроса
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getInd() {
		$args = func_get_args();
		$index = array_shift($args);
		$query = $this->prepareQuery($args);
		$ret = [];
		if ($res = $this->rawQuery($query)) {
			while ($row = $this->fetch($res)) {
				$ret[$row[$index]] = $row;
			}
		}
		return $ret;
	}
	
	/**
	 * Получение массива key => value (key из поля указанного в 1-м параметре, value из поля указанного в 2-м параметре), - результата запроса
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getIndField() {
		$args = func_get_args();
		$index = array_shift($args);
		$field = array_shift($args);
		$query = $this->prepareQuery($args);
		$ret = [];
		if ($res = $this->rawQuery($query)) {
			while ($row = $this->fetch($res)) {
				$ret[$row[$index]] = $row[$field];
			}
		}
		return $ret;
	}
	
	/**
	 * Получение массива скаляров, индексированный полем из первого параметра. Незаменимо для составления словарей вида key => value
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getIndCol() {
		$args = func_get_args();
		$index = array_shift($args);
		$query = $this->prepareQuery($args);
		$ret = [];
		if ($res = $this->rawQuery($query)) {
			while ($row = $this->fetch($res)) {
				$key = $row[$index];
				unset($row[$index]);
				$ret[$key] = reset($row);
			}
		}
		return $ret;
	}
	
	/**
	 * Получение строки с обработанными плейсхолдерами
	 *
	 * @return string
	 * @throws Exception
	 */
	public function parse() {
		return $this->prepareQuery(func_get_args());
	}
	
	/**
	 * Проверка переданого значения по белому списку
	 *
	 * @param array $input
	 * @param array $allowed
	 * @param bool $default
	 * @return string
	 */
	public function whiteList($input, $allowed, $default = false) {
		$found = array_search($input, $allowed);
		return ($found === false) ? $default : $allowed[$found];
	}
	
	/**
	 * Получение $input, с убранными из него все поля, отсутствующие в $allowed
	 *
	 * @param array $input
	 * @param array $allowed
	 * @return array
	 */
	public function filterArray($input, $allowed) {
		foreach (array_keys($input) as $v) {
			if (!in_array($v, $allowed)) {
				unset($input[$v]);
			}
		}
		return $input;
	}
	
	/**
	 * Получение последнего выполненного запроса
	 *
	 * @return string
	 */
	public function lastQuery() {
		$last = end($this->stats);
		return $last['query'];
	}
	
	/**
	 * Получение массива со всеми выполненными запросами и временем их выполнения
	 */
	public function getStats() {
		return $this->stats;
	}
	
	/**
	 * запрос в БД
	 *
	 * @param string $query
	 * @return mixed
	 * @throws Exception
	 */
	private function rawQuery($query) {
		$start = microtime(true);
		$res = $this->pdo->query($query);
		$timer = microtime(true) - $start;
		$this->stats[] = ['query' => $query, 'start' => $start, 'timer' => $timer];
		if (!$res) {
			$error = $this->pdo->errorInfo();
			end($this->stats);
			$key = key($this->stats);
			$this->stats[$key]['error'] = $error;
			$this->cutStats();
			$this->error('SQL_QUERY_ERROR', [$error, $query]);
		}
		$this->cutStats();
		return $res;
	}
	
	/**
	 * парсинг плейсхолдеров
	 *
	 * @param array $args
	 * @return string
	 * @throws Exception
	 *
	 * плейсхолдеры:
	 * ?s (string) - строки (а также DATE, FLOAT, DECIMAL)
	 * ?i (integer) - целые числа
	 * ?n (name) - имена полей и таблиц
	 * ?p (parsed) - для вставки уже обработанных частей запроса
	 * ?a (array) - набор значений для IN (строка вида 'a','b','c')
	 * ?u (update) - набор значений для SET (строка вида `field` = 'value', `field` = 'value')
	 */
	private function prepareQuery($args) {
		$query = '';
		$raw = array_shift($args);
		$array = preg_split('~(\?[nsiuap])~u', $raw, null, PREG_SPLIT_DELIM_CAPTURE);
		$anum = count($args);
		$pnum = floor(count($array) / 2);
		if ($pnum != $anum) {
			$this->error('SQL_ARGS_ERROR', [$anum, $pnum, $raw]);
		}
		foreach ($array as $k => $v) {
			if (($k % 2) == 0) {
				$query .= $v;
				continue;
			}
			$value = array_shift($args);
			switch ($v) {
				case '?n':
					$v = $this->escapeIdent($value);
					break;
				case '?s':
					$v = $this->escapeString($value);
					break;
				case '?i':
					$v = $this->escapeInt($value);
					break;
				case '?a':
					$v = $this->createIN($value);
					break;
				case '?u':
					$v = $this->createSET($value);
					break;
				case '?p':
					$v = $value;
					break;
			}
			$query .= $v;
		}
		return $query;
	}
	
	/**
	 * подготовка числа
	 *
	 * @param mixed $value
	 * @return float|boolean
	 * @throws Exception
	 */
	private function escapeInt($value) {
		if ($value === null) {
			return 'NULL';
		}
		if (!is_numeric($value)) {
			$this->error('SQL_PLACEHOLDER_I_ERROR', [gettype($value)]);
			return false;
		}
		if (is_float($value)) {
			$value = number_format($value, 0, '.', ''); // may lose precision on big numbers
		}
		return $value;
	}
	
	/**
	 * подготовка строки
	 *
	 * @param mixed $value
	 * @return string
	 */
	private function escapeString($value) {
		if ($value === null) {
			return 'NULL';
		}
		return $this->pdo->quote($value);
	}
	
	/**
	 * подготовка названий полей
	 *
	 * @param string $value
	 * @return string
	 * @throws Exception
	 */
	private function escapeIdent($value) {
		if (!$value) {
			$this->error('SQL_PLACEHOLDER_N_ERROR');
		}
		return '`'.str_replace('`', '``', $value).'`';
	}
	
	/**
	 * подготовка массива для IN
	 *
	 * @param array $data
	 * @return string
	 * @throws Exception
	 */
	private function createIN($data) {
		if (!is_array($data)) {
			$this->error('SQL_PLACEHOLDER_A_ERROR');
		}
		if (!$data) {
			return 'NULL';
		}
		$query = $comma = '';
		foreach ($data as $v) {
			$query .= $comma.$this->escapeString($v);
			$comma = ',';
		}
		return $query;
	}
	
	/**
	 * подготовка массива для SET
	 *
	 * @param array $data
	 * @return string
	 * @throws Exception
	 */
	private function createSET($data) {
		if (!is_array($data)) {
			$this->error('SQL_PLACEHOLDER_U_ERROR', [gettype($data)]);
		}
		if (!$data) {
			$this->error('SQL_PLACEHOLDER_U_ERROR2');
		}
		$query = $comma = '';
		foreach ($data as $k => $v) {
			$query .= $comma.$this->escapeIdent($k).'='.$this->escapeString($v);
			$comma = ',';
		}
		return $query;
	}
	
	/**
	 * обработка ошибок
	 *
	 * @param $code
	 * @param $args
	 * @throws Exception
	 */
	private function error($code, $args = []) {
		throw new Exception($code, $args);
	}
	
	/**
	 * статистика работы
	 */
	private function cutStats() {
		if (count($this->stats) > 100) {
			reset($this->stats);
			$first = key($this->stats);
			unset($this->stats[$first]);
		}
	}
}
