Класс для работы с БД
=====================
Простой класс для работы с базой данных.

Включает в себя плейсхолдеры и несколько функций для быстрого получения данных в нужном виде.

Требования:
-----------------------------------
- PHP 5.4+
- PDO
- MySQL

Начало работы
-----------------------------------

1. Устанавливаем c помощью Composer, либо подключаем файл \src\Connection.php через include/require.

2. Создаём объект Connection и передаём в него реквизиты доступа. При необходимости можно указать максимальное время ожидания и кодировку (по-умолчанию UTF8).

```php
$db = new \Programulin\Database\Connection('localhost', 'root', '', 'database', 60, 'UTF8');
```

Скрипт подключается к базе при выполнении 1-го запроса. Т.е. создание объекта Database не приводит к подключению к базе.

Максимальное время ожидания
-----------------------------------

Время ожидания указывает, сколько секунд должна ждать MySQL перед автоматическим закрытием соединения.

Допустим, вы указали время ожидания 30 секунд. Если в течение следующих 30 секунд вы не отправите ниодного запросе к базе, соединение будет автоматически закрыто. Попытка выполнить любой SQL-запрос после закрытия соединения приведёт к ошибке 'MySQL server has gone away.'.

Если ваши скрипты рассчитаны на выполнение в течение длительного времени, указывайте большое время ожидания, либо запустите переподключение вручную.

Пример:

```php
// Создаём экземпляр Database. Соединение с базой пока НЕ произошло.
$db = new Database('localhost', 'root', '', 'database', 60, 'UTF8');

// Пошёл 1-ый SQL-запрос. В этот момент происходит подключение к базе. Через 60 секунд соединение закроется.
$db->select('SELECT * FROM `table`');

// Прошло 50 секунд, запускаем ещё один запрос. Теперь у вас опять есть 60 секунд до закрытия соединения.
sleep(50);
$db->select('SELECT * FROM `table`');

// Прошло больше 60 секунд и соединение закрылось. Чтобы не получить ошибку, переподключаемся
// с помощью connect() и опять можем работать.
sleep(65);
$db->connect(); // Переподключение
$db->select('SELECT * FROM `table`');
```

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
$db->query('INSERT INTO :name (:names) VALUES (:s, :i) ', ['product', ['name', 'price'], 'Товар1', 1000]);
```

Через точку можно указать название базы данных, таблицы, столбца:

```php
$columns = ['database.table.column1', 'database.table.column2'];
$table = 'database.table';
$db->query('SELECT :names FROM :name', [$columns, $table]);
```

### :set

С помощью :set можно подставить в запрос целый массив:

```php
$product = [
	'name' => 'Телефон',
	'price' => 1500,
	'status' => false
];
  
$db->query('INSERT INTO `product` :set', [$product]);

$db->query('UPDATE `product` :set WHERE `product_id` = :i', [$product, 5]);
```

### :where

С помощью :where можно выполнить поиск по соответствию параметров:

```php
$search = [
	'price' => 1500,
	'status' => 1
];

$db->query('SELECT * FROM `product` :where', [$search]);
```

Если вместо массива значений передать пустоту, ключевое слово WHERE не будет добавлено в SQL-запрос. Следующие запросы приведут к одинаковому результату:

```php
$search = [];

$res1 = $db->query('SELECT * FROM `product`');
$res2 = $db->query('SELECT * FROM `product` :where', [$search]);
```

### :in

С помощью :in можно подставить массив значений в оператор IN:

```php
$values = ['Товар1, Товар2, Товар3'];

$db->query('SELECT * FROM `product` WHERE `name` :in', [$values]);
```

Если в качестве значения передать пустоту (в том числе пустой массив), в SQL запросе это отобразится как IN(false) и условие не будет обработано. Другими словами, следующие запросы приведут к одинаковому результату:

```php
$values = [];

$res1 = $db->query('SELECT * FROM `product`');
$res2 = $db->query('SELECT * FROM `product` WHERE `name` :in', [$values]);
```

### :limit

Лимит можно указывать с помощью плейсхолдеров :i и :limit.

```php
$db->query('SELECT * FROM `product` :limit', [1]);
$db->query('SELECT * FROM `product` :limit', [[2,2]]);
$db->query('SELECT * FROM `product` LIMIT :i,:i', [2,2]);
```

При передаче пустоты, в том числе массива с нулевыми значениями, LIMIT не добавится в SQL-запрос. Следующие запросы приведут к одинаковому результату:

```php
$db->query('SELECT * FROM `product`');
$db->query('SELECT * FROM `product` :limit', [0]);
$db->query('SELECT * FROM `product` :limit', [[0,0]]);
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
$db->selectKeyPair('SELECT `product_id`, `name` FROM `product`');
```

На выходе будет нечто вроде:
```html
Array
(
    [1] => Товар 1
    [2] => Товар 2
    [3] => Товар 3
)
```

### selectGroup

Получение записей, сгруппированных по первому столбцу:

```php
$db->selectGroup('SELECT `status`, `name`, `price` FROM `product`');
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

ChangeLog:
-----------------------------------

### 1.1
- Возможность указания кодировки соединения.
- В названиях полей и таблиц можно использовать точки. Например, плейсхолдер :where корректно поймёт условие ['database.product.name' => 'Название товара'].
- Плейсхолдеры :set, :where, :in теперь сами добавляют ключевые слова SET, WHERE, IN в sql-запрос.
- В плейсхолдеры :where, :in и :limit теперь можно передать пустоту ('0', пустой массив и т.п.). Для :limit пустотой также считаются значения [0] и [0,0]. Результат будет аналогичен выполнению SQL-запроса без плейсхолдеров.
