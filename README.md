Класс для работы с БД
=====================
Простой класс для работы с базой данных.

Включает в себя плейсхолдеры и несколько функций для быстрого получения данных в нужном виде.

Требования:
-----------------------------------
- PHP версии 5.4+
- PDO
- MySQL

Подключение
-----------------------------------

Подключаем скрипт и указываем реквизиты доступа (хост, логин, пароль, база данных).

5-ый параметр необязателен, указывает максимальное время ожидания ответа от базы (см. wait_timeout) в секундах.

```php
require('Database.php');

$db = new Database('localhost', 'root', '', 'test', 60);
```

Скрипт подключается к базе при выполнении 1-го запроса. Т.е. создание объекта Database не приводит к подключению к базе.

Плейсхолдеры
-----------------------------------
Первым параметром передаём SQL-запрос:

```php
$db->query('DELETE FROM `product`');
```

Если в SQL-запросе есть плейсхолдеры, вторым параметром передаём массив значений, которые нужно подставить:

```php
$db->query('UPDATE `product` SET `price` = :i WHERE `product_id` = :i', [150, 5]);
```
### :v :s :b :i :d

Перед вставкой значения можно привести его к нужному типу:
- :s (string) - приводит к строке
- :b (bool) - приводит к булеву типу
- :i (int) - приводит к целому числу
- :d (double) - приводит к дробному числу
- :v (value) - не изменяет тип

```php
$db->query('UPDATE `product` SET `name` = :s, `price` = :d WHERE `product_id` = :i', ['Новое название', 5.5, 10]);
```
### :name, names

С помощью :name можно подставить название таблицы или столбца. :names подставляет несколько названий через запятую:

```php
$db->query('INSERT INTO :name (:names) VALUES (:s, :i, :s) ', ['product', ['name', 'price'], 'Товар1', 1000]);
```

### :set

С помощью :set можно подставить в запрос целый массив:

```php
$product = [
	'name' => 'Телефон',
	'price' => 1500,
	'status' => false
];
  
$db->query('INSERT `product` SET :set', [$product]);

$db->query('UPDATE `product` SET :set WHERE `product_id` = :i', [$product, 5]);
```

### :where

С помощью :where можно выполнить поиск по соответствию параметров:

```php
	$search = [
		'price' => 1500,
		'status' => 1
	];
	
	$db->query('SELECT * FROM `product` WHERE :where', [$search]);
```

### :in

Подстановка параметров в IN:

```php
$names = ['Товар1, Товар2, Товар3'];

$db->query('SELECT * FROM `product` WHERE `name` IN :in', [$names]);
```

### :limit

Лимит можно указывать с помощью плейсхолдеров :i или :limit.

```php
$db->query('SELECT * FROM `product` :limit', [1]);
$db->query('SELECT * FROM `product` :limit', [[2,2]]);
$db->query('SELECT * FROM `product` LIMIT :i,:i', [2,2]);
```

Методы
-----------------------------------

### select

Получение множества строк в виде ассоциативного массива:

```php
$products = $db->select('SELECT * FROM `product`');
```

### selectRow

Получение одной записи, либо null:

```php
$product = $db->selectRow('SELECT * FROM `product` WHERE `product_id` = :i', [15]);
```

### selectOne

Получение 1-го столбца 1-ой строки:

```php
$max = $db->selectOne('SELECT MAX(`price`) FROM `product`');
$count = $db->selectOne('SELECT COUNT(*) FROM `product`');
```

### selectKeyPair

Получение массива, в котором первая колонка - ключи, вторая - значения:

```php
$db->selectKeyPair('SELECT `name`, `price` FROM `product`');
```

На выходе будет нечто вроде:
```html
Array
(
    [Товар 1] => 1500
    [Товар 2] => 1600
    [Товар 3] => 1700
    [Телефон] => 1500
)
```

### selectGroup

Получение записей, сгруппированных по первому столбцу:

```php
$db->selectGroup('SELECT `status`, `name` FROM `product`');
```

На выходе будет нечто вроде:

```html
Array
(
    [1] => Array
        (
            [0] => Array
                (
                    [name] => Товар 1
                    [price] => 1500
                )
        )
    [0] => Array
        (
            [0] => Array
                (
                    [name] => Товар 2
                    [price] => 1600
                )
            [1] => Array
                (
                    [name] => Товар 3
                    [price] => 1700
                )
        )
)
```

В этом примере все записи разбиты на 2 подмассива, со status = 1 и status = 0.

### selectColumn

Получение всех значений одного столбца.

```php
$db->selectColumn('SELECT `name` FROM `product`');
```

На выходе:

```html
Array
(
    [0] => Товар 1
    [1] => Товар 2
    [2] => Товар 3
)
```

### query

Выполнение запроса и получение экземпляра PDOStatement:

```php
$stmt = $db->query('SELECT * FROM `products`');
```

### update, delete

Выполнение запроса и получение количества затронутых строк:

```php
$count = $db->update('UPDATE `product` SET `status` = 1');

$count = $db->delete('DELETE FROM `product`');
```

### insert

Выполнение INSERT-запроса и получение последнего инкрементального id:

```
$id = $db->insert('INSERT INTO `product` SET `name` = :s', ['Новый товар']);
```

### pdo

Получение исходного экземпляра PDO:

```php
$pdo = $db->pdo();
```

### lastInsertId

Получение последнего автоинкрементального id:

```php
$id = $db->lastInsertId();
```

### connect

Подключение/переподключение к БД:

```php
$db->connect();
```

### close

Уничтожение экземпляра PDO:

```php
$db->close();
```
