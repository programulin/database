<?php
namespace Programulin\Database;

use PDO;

/**
 * Класс для работы с базой данных.
 * 
 * @version 1.2.0
 * @link https://github.com/web-dynamics/database
 */
class Connection
{

    private $host;
    private $login;
    private $password;
    private $database;
    private $charset;
    private $timeout;
    private $pdo;
    private $parser;

    /**
     * Создание нового подключения.
     * 
     * @param string $host
     * @param string $login
     * @param string $password
     * @param string $database
     * @param int $timeout
     * @param string $charset
     */
    public function __construct($host, $login, $password, $database, $timeout = 0, $charset = 'UTF8')
    {
        $this->host = $host;
        $this->login = $login;
        $this->password = $password;
        $this->database = $database;
        $this->timeout = $timeout;
        $this->charset = $charset;
        $this->parser = new QueryParser();
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
        if (!$this->pdo)
            $this->connect();

        if (!empty($params))
        {
            $this->parser->parse($query, $params);
            $stmt = $this->pdo->prepare($this->parser->query());

            if (!empty($this->parser->params()))
            {
                foreach ($this->parser->params() as $k => $v)
                {
                    if (is_numeric($v))
                        $type = PDO::PARAM_INT;
                    elseif (is_null($v))
                        $type = PDO::PARAM_NULL;
                    elseif (is_bool($v))
                        $type = PDO::PARAM_BOOL;
                    else
                        $type = PDO::PARAM_STR;

                    $stmt->bindValue($k + 1, $v, $type);
                }
            }

            $stmt->execute();
            return $stmt;
        } else
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
        $dsn = "mysql:host={$this->host};dbname={$this->database};charset={$this->charset}";

        $options = [
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        $this->close();
        $this->pdo = new PDO($dsn, $this->login, $this->password, $options);

        if ($this->timeout)
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
}