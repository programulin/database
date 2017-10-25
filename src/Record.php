<?php

namespace Programulin\Database;

use Programulin\Database\Exceptions\Record as RecordException;

class Record
{
	protected static $factory;
	protected $params = [];

	/**
	 * Получение названия таблицы в БД.
	 * 
	 * @return string
	 */
	public static function table()
	{
		$class = explode('\\', get_called_class());
		return strtolower(array_pop($class));
	}

	/**
	 * Получение названия подключения к БД.
	 * 
	 * @return string|null
	 */
	public static function conn()
	{
		return null;
	}

	/**
	 * Получение названия первичного ключа таблицы.
	 * 
	 * @return string
	 */
	public static function key()
	{
		return 'id';
	}

	/**
	 * Установка собственной функции генерации объектов.
	 * 
	 * @param callable $factory
	 */
	public static function factory(callable $factory = null)
	{
		static::$factory = $factory;
	}

	/**
	 * Создание нового объекта.
	 * 
	 * @param array $params Параметры в виде ассоциативного массива.
	 * @return object
	 */
	public static function create(array $params = [])
	{	
		if(is_callable(static::$factory))
			$func = static::$factory;
		else
			$func = function($class) { return new $class; };

		$object = $func(get_called_class());

		if (!empty($params))
			$object->import($params);

		return $object;
	}

	/**
	 * Поиск записей. Начало запроса вида 'SELECT * FROM table' писать НЕ нужно,
	 * эта часть подставляется автоматически.
	 * 
	 * @param string $sql
	 * @param array $params
	 * @return array Массив объектов.
	 */
	public static function find($sql = '', array $params = [])
	{
		$query = 'SELECT * FROM :name ' . $sql;
		array_unshift($params, static::table());

		$stmt = static::db()->query($query, $params);

		$objects = [];

		while ($row = $stmt->fetch())
			$objects[] = static::create($row);

		return $objects;
	}

	/**
	 * Поиск одной записи. Начало запроса вида 'SELECT * FROM table' писать НЕ нужно,
	 * эта часть подставляется автоматически.
	 * 
	 * @param type $sql
	 * @param array $params
	 * @return object|null
	 */
	public static function findOne($sql = '', array $params = [])
	{
		$result = static::find($sql, $params);
		
		return isset($result[0]) ? $result[0] : null;
	}
	
	/**
	 * Поиск записи по идентификатору. Возвращает объект или null.
	 * 
	 * @param int $id
	 * @return object|null
	 */
	public static function findById($id)
	{
		$sql = 'SELECT * FROM :name WHERE :name = :i';
		$params = static::db()->selectRow($sql, [static::table(), static::key(), $id]);

		return !empty($params) ? static::create($params) : null;
	}

	/**
	 * Возвращает найденную запись, либо пустой объект.
	 * 
	 * @param int $id
	 * @return object
	 */
	public static function findByIdOrCreate($id)
	{
		$sql = 'SELECT * FROM :name WHERE :name = :i';
		$params = static::db()->selectRow($sql, [static::table(), static::key(), $id]);

		if(!$params)
			$params = [];

		return static::create($params);
	}

	/**
	 * Поиск записей по полному SQL-запросу.
	 * 
	 * @param string $sql
	 * @param array $params
	 * @return array
	 */
	public static function findBySql($sql, array $params = [])
	{
		$stmt = static::db()->query($sql, $params);

		$objects = [];

		while ($row = $stmt->fetch())
			$objects[] = static::create($row);

		return $objects;
	}

	/**
	 * Получение количества записей. Начало запроса вида 'SELECT COUNT(*) FROM table' писать НЕ нужно,
	 * эта часть подставляется автоматически.
	 * 
	 * @param string $sql 
	 * @param array $params
	 * @return int
	 */
	public static function count($sql = '', array $params = [])
	{
		$query = 'SELECT COUNT(*) FROM :name ' . $sql;
		array_unshift($params, static::table());

		return (int) static::db()->selectOne($query, $params);
	}

	/**
	 * Поиск записей по параметрам с последующим удалением. Начало запроса вида 'SELECT * FROM table' писать НЕ нужно.
	 * 
	 * @param string $sql
	 * @param array $params
	 */
	public static function findAndDelete($sql = '', $params = [])
	{
		$items = static::find($sql, $params);

		foreach ($items as $item)
			$item->delete();
	}

	public function __get($name)
	{
		return isset($this->params[$name]) ? $this->params[$name] : null;
	}

	public function __set($name, $value)
	{
		$this->params[$name] = $value;
	}

	/**
	 * Получение идентификатора записи.
	 * 
	 * @return int|null
	 */
	public function id()
	{
		$column = static::key();
		return $this->$column;
	}
	
	/**
	 * Провека существования параметра записи.
	 * 
	 * @param string $name
	 * @return bool
	 */
	public function has($name)
	{
		return array_key_exists($name, $this->params);
	}

	/**
	 * Импорт параметров.
	 * 
	 * Если $is_blacklist = true, то из $values будут удалены все ключи, указанные в $list.
	 * Если $is_blacklist = false, то в $values останутся только те ключи, которые указаны в $list.
	 * 
	 * @param array $values Ассоциативный массив с параметрами.
	 * @param array $list Массив из ключей для фильтрации.
	 * @param bool $is_blacklist
	 */
	public function import(array $values, array $list = [], $is_blacklist = false)
	{
		if(!empty($list))
		{
			if($is_blacklist)
			{
				foreach($values as $k => $v)
					if(in_array($k, $list, true))
						unset($values[$k]);
			}
			else
			{
				foreach($values as $k => $v)
					if(!in_array($k, $list, true))
						unset($values[$k]);
			}
		}

		foreach($values as $k => $v)
			$this->params[$k] = $v;
	}

	/**
	 * Экспорт параметров.
	 * 
	 * Если $is_blacklist = true, то функция вернёт все параметры кроме тех, что указаны в $list.
	 * Если $is_blacklist = false, то функция вернёт только те параметры, что указаны в $list.
	 * 
	 * @param array $list
	 * @param bool $is_blacklist
	 * @return array
	 */
	public function export(array $list = [], $is_blacklist = false)
	{
		$result = [];
		
		if(!empty($list))
		{
			if($is_blacklist)
			{
				$result = $this->params;
				
				foreach($list as $v)
					if(isset($result[$v]))
						unset($result[$v]);
			}
			else
			{
				foreach($list as $v)
					if(isset($this->params[$v]))
						$result[$v] = $this->params[$v];
			}
				
		}
		else
			$result = $this->params;
		
		return $result;
	}

	/**
	 * Подгрузка из БД актуальных параметров.
	 * 
	 * @throws RecordException
	 */
	public function refresh()
	{
		if (empty($this->params[static::key()]))
			throw new RecordException('Попытка обновления состояния записи с пустым ключом.');

		$query = 'SELECT * FROM :name WHERE :name = :i';

		$state = static::db()->selectRow($query, [static::table(), static::key(), $this->params[static::key()]]);

		if (empty($state))
			throw new RecordException('Попытка обновления состояния несуществующей записи.');

		$this->import($state);
	}

	/**
	 * Выполнение каких-либо действий перед сохранением записи.
	 */
	protected function beforeSave()
	{

	}

	/**
	 * Выполнение каких-либо действий после сохранения записи.
	 */
	protected function afterSave()
	{
		
	}

	/**
	 * Выполнение каких-либо действий перед удалением записи.
	 */
	protected function beforeDelete()
	{
		
	}

	/**
	 * Сохранение записи.
	 */
	public function save()
	{
		$this->beforeSave();

		if (empty($this->params[static::key()]))
			$this->insert();
		else
			$this->update();

		$this->afterSave();
	}

	protected function update()
	{
		$query = 'UPDATE :name :set WHERE :name = :i';

		$params = $this->params;
		unset($params[static::key()]);

		static::db()->update($query, [static::table(), $params, static::key(), $this->params[static::key()]]);
	}

	protected function insert()
	{
		$params = $this->params;

		if (empty($params))
			$params[static::key()] = null;

		$this->params[static::key()] = static::db()->insert('INSERT INTO :name :set', [static::table(), $params]);
	}

	/**
	 * Удаление записи.
	 * 
	 * @throws RecordException
	 */
	public function delete()
	{
		if (!isset($this->params[static::key()]))
			throw new RecordException('Попытка удаления записи с пустым ключом.');

		$this->beforeDelete();

		static::db()->delete('DELETE FROM :name WHERE :name = :i', [static::table(), static::key(), $this->params[static::key()]]);
	}

	protected static function db()
	{
		return Manager::conn(static::conn());		
	}
}