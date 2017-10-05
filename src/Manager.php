<?php
namespace Programulin\Database;

use Programulin\Database\Exceptions\Manager as ManagerException;

class Manager
{
	private static $default;
	private static $connections = [];

	/**
	 * Указание параметров подключения к одной базе данных.
	 * 
	 * @param array $config
	 */
	public static function config(array $config)
	{
		self::$connections[$config['name']] = new Connection(
			$config['host'],
			$config['login'],
			$config['password'],
			$config['database'],
			isset($config['charset']) ? $config['charset'] : 'UTF8',
			isset($config['timeout']) ? $config['timeout'] : null
		);
		
		if(!empty($config['default']))
			self::$default = $config['name'];
	}

	/**
	 * Указание параметров подключения к нескольким базам данных.
	 * 
	 * @param array $configs
	 */
	public static function configs(array $configs)
	{
		foreach($configs as $config)
			self::config($config);
	}

	/**
	 * Получение соединения с БД по его названию. Если не указать название, будет выбрано
	 * подключение по-умолчанию.
	 * 
	 * @param string $name
	 * @return object
	 * @throws ManagerException
	 */
	public static function conn($name = null)
	{
		if(is_null($name))
		{
			if(is_null(self::$default))
				throw new ManagerException('Не указано подключение по-умолчанию.');

			$name = self::$default;
		}
		
		if(!isset(self::$connections[$name]))
			throw new ManagerException("Не найдено подключение с названием '$name'.");
		
		return self::$connections[$name];
	}

	/**
	 * Установка собственной функции генерации объектов.
	 * 
	 * @param callable $func
	 */
	public static function factory(callable $func)
	{
		Record::factory($func);
	}
}