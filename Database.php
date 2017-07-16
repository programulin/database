<?php
/**
 * Класс для работы с базой данных.
 */
class Database
{
	private $host;
	private $login;
	private $pass;
	private $db;
	private $timeout;
	private $pdo;
	private $input_params = [];
	private $output_params = [];
	private $output_query;

	/**
	 * Создание нового подключения.
	 * 
	 * @param string $host
	 * @param string $login
	 * @param string $password
	 * @param string $database
	 * @param int $timeout Максимальное время ожидания следующего запроса (wait_timeout).
	 */
	public function __construct($host, $login, $pass, $db, $timeout = null)
	{
		$this->host = $host;
		$this->login = $login;
		$this->pass = $pass;
		$this->db = $db;
		$this->timeout = $timeout;
	}

	/**
	 * Выполняет SELECT запрос и возвращает массив со всеми строками.
	 * 
	 * @param string $query
	 * @param array $params
	 * @return array
	 */
	public function select($query, array $params = [])
	{
		return $this->query($query, $params)->fetchAll();
	}

	/**
	 * Выполненяет SELECT запрос и возвращает первую строку в виде ассоциативного массива.
	 * 
	 * @param string $query
	 * @param array $params
	 * @return array
	 */
	public function selectRow($query, array $params = [])
	{
		return $this->query($query, $params)->fetch();
	}

	/**
	 * Выполняет SELECT запрос и возвращает первый столбец первой строки.
	 * 
	 * @param string $query
	 * @param array $params
	 * @return mixed
	 */
	public function selectOne($query, array $params = [])
	{
		return $this->query($query, $params)->fetchColumn();
	}

	/**
	 * Выполняет SELECT запрос и возвращает массив, в котором 1-ая указанная в SQL-запросе
	 * колонка становится ключами, а вторая колонка - значениями (см. PDO::FETCH_KEY_PAIR).

	 * @param string $query
	 * @param array $params
	 * @return array
	 */
	public function selectKeyPair($query, array $params = [])
	{
		return $this->query($query, $params)->fetchAll(PDO::FETCH_KEY_PAIR);
	}

	
	/**
	 * Выполняет SELECT запрос и возвращает записи с группировкой по первому столбцу (см. PDO::FETCH_GROUP).

	 * @param string $query SQL-запрос SELECT.
	 * @param array $params Значения для плейсхолдеров.
	 * @return array Результат выборки.
	 */
	public function selectGroup($query, array $params = [])
	{
		return $this->query($query, $params)->fetchAll(PDO::FETCH_GROUP);
	}

	/**
	 * Выполненяет SELECT запрос и возвращает все значения 1-ой колонки.

	 * @param string $query SQL-запрос SELECT.
	 * @param array $params Значения для плейсхолдеров.
	 * @return array Результат выборки.
	 */
	public function selectColumn($query, array $params = [])
	{
		return $this->query($query, $params)->fetchAll(PDO::FETCH_COLUMN);
	}
	
	/**
	 * Выполняет UPDATE запрос и возвращает количество затронутых строк.
	 * 
	 * @param string $query
	 * @param array $params
	 * @return int
	 */
	public function update($query, array $params = [])
	{
		return (int) $this->query($query, $params)->rowCount();
	}

	/**
	 * Выполняет INSERT запрос и возвращает последний автоинкрементный ID.
	 * 
	 * @param string $query
	 * @param array $params
	 * @return int
	 */
	public function insert($query, array $params = [])
	{
		$this->query($query, $params);
		return $this->lastInsertId();
	}

	/**
	 * Выполняет DELETE запрос и возвращает количество затронутых строк.
	 * 
	 * @param string $query
	 * @param array $params
	 * @return int
	 */
	public function delete($query, array $params = [])
	{
		return (int) $this->query($query, $params)->rowCount();
	}

	/**
	 * Выполнение SQL-запроса и получение PDOStatement.
	 * 
	 * @param string $query
	 * @param array $params Значения для плейсхолдеров.
	 * @return object Объект PDOStatement
	 */
	public function query($query, array $params = [])
	{
		if(!$this->pdo)
			$this->connect();

		if(!empty($params))
		{
			$this->parseQuery($query, $params);
			$stmt = $this->pdo->prepare($this->output_query);
			
			if(!empty($this->output_params))
			{
				foreach($this->output_params as $k => $v)
				{
					if(is_numeric($v))
						$type = PDO::PARAM_INT;
					elseif(is_null($v))
						$type = PDO::PARAM_NULL;
					elseif(is_bool($v))
						$type = PDO::PARAM_BOOL;
					else
						$type = PDO::PARAM_STR;

					$stmt->bindValue($k + 1, $v, $type);
				}
			}

			$stmt->execute();
			return $stmt;
		}
		else
			return $this->pdo->query($query);
	}

	/**
	 * Получение объекта PDO.
	 * 
	 * @return object
	 */
	public function pdo()
	{
		return $this->pdo;
	}

	/**
	 * Подключение к базе данных.
	 * @return type
	 */
	public function connect()
	{
		$dsn = "mysql:host={$this->host};dbname={$this->db};charset=UTF8";

		$options = [
			PDO::ATTR_EMULATE_PREPARES => true,
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		];

		$this->pdo = new PDO($dsn, $this->login, $this->pass, $options);

		if(isset($this->timeout))
			$this->pdo->query("SET wait_timeout={$this->timeout}");
	}

	/**
	 * Закрытие соединения с БД.
	 */
	public function close()
	{
		$this->pdo = null;
	}

	public function lastInsertId()
	{
		return (int) $this->pdo->lastInsertId();
	}

	private function parseQuery($query, array $params)
	{
		$this->input_params = $params;
		$this->output_params = [];
		
		$this->output_query = preg_replace_callback('~(:[a-z]{1,})~s', [$this, 'placeholderHandler'], $query);

		if(!empty($this->input_params))
			throw new Exception('Значений больше, чем плейсхолдеров.');
	}

	private function placeholderHandler(array $ph)
	{
		$param = array_shift($this->input_params);
		$ph = $ph[0];
		
		if(is_null($param))
			throw new Exception("Для плейсхолдера '{$ph}' не найдено значение.");
		
		# :v :s :b :i, :d
		if(in_array($ph, [':v', ':s', ':b', ':i', ':d'], true))
		{
			if(!$this->validateValue($param))
				throw new Exception('Некорректное значение плейсхолдера :v.');

			if($ph === ':s')
				$param = (string) $param;
			elseif($ph === ':b')
				$param = (bool) $param;
			elseif($ph === ':i')
				$param = (int) $param;
			elseif($ph === ':d')
				$param = (float) $param;
			
			$this->output_params[] = $param;
			return '?';
		}

		# :name
		elseif($ph === ':name')
		{
			if(!$this->validateName($param))
				throw new Exception('Некорректное значение плейсхолдера :name.');
			
			return "`$param`";
		}

		# :names
		elseif($ph === ':names')
		{
			if(!is_array($param) or empty($param))
				throw new Exception('Значение плейсхолдера :names должно быть непустым массивом.');
			
			foreach($param as $name)
			{
				if(!$this->validateName($name))
					throw new Exception('Некорректное значение плейсхолдера :names.');
			
				$names[] = "`$name`";
			}

			return implode(',', $names);
		}
		
		# :set
		elseif($ph === ':set')
		{
			if(!is_array($param) or empty($param))
				throw new Exception('Значение плейсхолдера :set должно быть непустым массивом.');

			foreach($param as $name => $value)
			{
				if(!$this->validateName($name))
					throw new Exception('Некорректный ключ одного из параметров :set.');
				
				if(!$this->validateValue($value))
					throw new Exception('Некорректное значение одного из параметров :set.');

				$this->output_params[] = $value;
				$sets[] =  "`{$name}` = ?";
			}

			return implode(',', $sets);
		}

		# :where
		elseif($ph === ':where')
		{	
			if(!is_array($param) or empty($param))
				throw new Exception('Значение плейсхолдера :where должно быть непустым массивом.');

			foreach($param as $name => $value)
			{
				if(!$this->validateName($name))
					throw new Exception('Некорректный ключ одного из параметров :where.');
				
				if(!$this->validateValue($value))
					throw new Exception('Некорректное значение одного из параметров :where.');

				$this->output_params[] = $value;
				$where[] =  "`{$name}` = ?";
			}

			return implode(' AND ', $where);
		}

		# :in
		elseif($ph === ':in')
		{
			if(!is_array($param) or empty($param))
				throw new Exception('Значение плейсхолдера :in должно быть непустым массивом.');

			foreach($param as $value)
			{
				if(!$this->validateValue($value))
					throw new Exception('Некорректное значение одного из параметров :in.');

				$this->output_params[] = $value;
				$in[] = '?';
			}
			return '(' . implode(',', $in) . ')';
		}

		# :limit
		elseif($ph === ':limit')
		{
			if(!is_array($param))
				$param = [$param];
			
			if(empty($param) or count($param) > 2)
				throw new Exception('Некорректное значение плейсхолдера :limit.');
			
			foreach($param as $value)
			{
				if(!$this->validateValue($value))
					throw new Exception('Один из параметров :limit некорректный.');

				$limits[] = (int) $value;
			}

			return 'LIMIT ' . implode(',', $limits);
		}

		# wrong name
		else
			throw new Exception("Некорректный плейсхолдер '{$ph}'.");
	}
	
	private function validateValue($value)
	{
		return in_array(gettype($value), ['boolean', 'integer', 'double', 'string', 'NULL'], true);
	}
	
	private function validateName($value)
	{
		if(!in_array(gettype($value), ['string', 'double', 'integer'], true))
			return false;

		if(!preg_match('~^[a-z0-9\-_]+$~i', $value))
			return false;
		
		return true;
	}
}