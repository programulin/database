Библиотека для работы с MySQL.
=====================

Библиотека включает в себя 3 компонента:

1) Плейсхолдеры:

```php
$db->select('SELECT * FROM :name WHERE :name > :i', ['products', 'price', 5000]);

$params = [
    'name' => 'Товар',
    'price' => 1000
];

$db->insert('UPDATE `products` :set WHERE `id` = :i', [$params, 10]);
```

2) Функции для быстрого получения данных в нужном виде: selectRow, selectOne, selectColumn, selectKeyPair и т.п.

3) Простенькая реализация ActiveRecord.

Требования:
-----------------------------------
- PHP 5.4+
- PDO
- MySQL

Начало работы
-----------------------------------

1) Устанавливаем c помощью Composer:

```
composer require programulin/database
```

2) Указываем реквизиты доступа к одной или нескольким БД:

```php
use Programulin\Database\Manager;

// Для одного соединения:
Manager::config([
	'name'     => 'db1',
	'default'  => true,
	'login'    => 'root',
	'password' => '',
	'database' => 'db1',
	'host'     => 'localhost',
	'charset'  => 'UTF8', // Необязательно, по-умолчанию UTF8
	'timeout'  => 600 // Необязательно, изменяет параметр wait_timeout
]);

// Для нескольких соединений:
$config[] = [
	'name'     => 'db1',
	'default'  => true,
	'login'    => 'root',
	'password' => '',
	'database' => 'db1',
	'host'     => 'localhost',
	'timeout'  => 600
];

$config[] = [
	'name'     => 'db2',
	'login'    => 'root',
	'password' => '',
	'database' => 'db2',
	'host'     => 'localhost'
];

Manager::configs($config);
```

3) При необходимости передаём фабричный метод генерации новых объектов:

```php
Manager::factory(function($class){
	return new $class();
});
```

4) Получаем объект соединения с БД:

```php
// Если не указать название, будет выбрано соединение по-умолчанию.
$db = Manager::conn('db1');
```

Подключение к БД происходит при выполнении 1-го запроса. Т.е. вызов conn() не приводит к подключению к базе.

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

С помощью :where можно сделать поиск с динамическим количеством параметров:

```php
$where = [
	['status', '>=', 1],
	['price', 'between', [500, 1000]]
];

if(!empty($_POST['keyword']))	
	$where[] = ['name', 'like', "%{$_POST['keyword']}%"];

$db->query('SELECT * FROM `product` :where', [$where]);
```

Первым параметром передаётся название поля, вторым действие (>=, >, <=, <, =, !=, like, between, in), третьим значения.

Для between третьим параметром необходимо передать массив из 2 значений, для in - массив любого размера.

Если передать пустой массив, ключевое слово WHERE не будет добавлено в SQL-запрос. Следующие запросы приведут к одинаковому результату:

```php
$search = [];

$res1 = $db->query('SELECT * FROM `product`');
$res2 = $db->query('SELECT * FROM `product` :where', [$search]);
```

### :in

С помощью :in можно подставить массив значений в оператор IN:

```php
$values = ['Товар1', 'Товар2', 'Товар3'];

$db->query('SELECT * FROM `product` WHERE `name` :in', [$values]);
```

Если в качестве значения передать пустоту (в том числе пустой массив), в SQL запросе это отобразится как IN(false) и условие не будет обработано. Следующие запросы приведут к одинаковому результату:

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

ActiveRecord
-----------------------------------
Класс модели должен наследоваться от Programulin\Database\Record. По-умолчанию настройки класса принимают следующий вид:

1. Название таблицы берётся из последнего элемента пространства имён в нижнем регистре(для класса App\Models\Product_Image таблица будет product_image),
2. Первичный ключ - id,
3. Подключение к БД - то, что было указано в конфиге по-умолчанию.

Все эти настройки можно менять:

```php
use Programulin\Database\Record;

class Product extends Record
{
    public static function conn()
    {
        return 'db2';
    }

    public static function table()
    {
        return 'product';
    }

    public static function key()
    {
        return 'product_id';
    }
}
```

### Поиск записей.
```php
// Получение одной записи по id.
$product = Product::findById(10);

// Получение по SQL-запросу. Начало вида 'SELECT * FROM `table`' писать не нужно.
$product = Product::findOne('WHERE `price` > :i LIMIT 1', [1000]);

// Получение всех записей.
$products = Product::find();

// Получение записей по характеристикам.
$where = [
	['price', 'between', [500, 1000]],
	['status', '=', 1]
];

$products = Product::find(':where LIMIT 10', [$where]);

// Получение записей по SQL-запросу.
$products = Product::findBySql('SELECT * FROM `product` LIMIT 50');

// Получение количества записей.
$count_all = Product::count();
$count_active = Product::count('WHERE `status` = 1');
```

### Создание, изменение и удаление записей.
```php
// Создание записи
$product = Product::create();

// Изменение параметров
$product->name = 'Название';
$product->price = 1000;

// Сохранение
$product->save();

// Получение актуальной информации из БД по идентификатору:
$product->id = 10;
$product->refresh();

// Удаление
$product->delete();

// Удаление множества записей:
Product::findAndDelete('WHERE `price` < :i', [1000]);
```

### Управление параметрами.
```php
$product = Product::findById(15);

// Получение идентификатора записи
$id = $product->id();

$params = [
	'name' => 'Новое название'
	'price' => 1000,
	'status' => 1
];

// Импорт всех параметров
$product->import($params);

// Импорт только указанных параметров
$product->import($params, ['name', 'price']);

// Импорт всех параметров, кроме указанных
$product->import($params, ['status'], true);

// Создание записи и импорт параметров
$product = Product::create(['name' => 'Название']);
$product = Product::create(['name' => 'Название'], ['name'], true);

// Экспорт всех параметров
$options = $product->export();

// Экспорт только указанных параметров
$options = $product->export(['name', 'price']);

// Экспорт всех параметров, кроме указанных
$options = $product->export(['status'], true);

// Проверка существования параметра
if(!$product->has('price'))
    $product->price = 50;
```

### События сохранения и удаления записи.
Вы можете переопределить методы beforeSave(), beforeDelete() и afterSave() для добавления валидации и прочего функционала:

```php
use Programulin\Database\Record;

class Product extends Record
{
    private $saves;

    protected function beforeSave()
    {
        if(!$this->has('price'))
            throw new \Exception('Отсутствует цена!');
	}

    protected function afterSave()
    {
        $this->saves++;
    }

    protected function beforeDelete()
    {
        if($this->has('price') and $this->price == 1)
            throw new \Exception('Нельзя удалять включённые товары.');
    }
}
```