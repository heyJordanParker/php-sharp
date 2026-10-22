--TEST--
PHP# Database runs SQL through PDO with parameters and transactions, and Cache keeps strings in a SQLite file until they expire
--EXTENSIONS--
pdo_sqlite
--FILE--
<?php
require __DIR__ . '/effect_classes.inc';
require __DIR__ . '/Ledger.sharp';

use Sharp\Time\Duration;

$database = new Sharp\Data\Database('sqlite::memory:');
var_dump($database->execute('CREATE TABLE orders (id INTEGER PRIMARY KEY, sku TEXT, cents INTEGER)'));
var_dump($database->execute('INSERT INTO orders (sku, cents) VALUES (?, ?), (?, ?)', ['tea', 450, 'cake', 300]));
$rows = $database->query('SELECT sku, cents FROM orders ORDER BY id');
var_dump(count($rows), get_debug_type($rows[0]));
var_dump($database->query('SELECT sku FROM orders WHERE cents > ?', [1000]));
var_dump(get_debug_type($database->queryFirst('SELECT sku FROM orders WHERE cents < ?', [400])), $database->queryFirst('SELECT sku FROM orders WHERE id = 9'));
$ledger = new Demo\Ledger($database);
var_dump($ledger->skus(), $ledger->cheapest());
var_dump($database->scalar('SELECT SUM(cents) FROM orders'), $database->scalar('SELECT sku FROM orders WHERE id = 9'));

$database->beginTransaction();
$database->execute('DELETE FROM orders');
$database->rollback();
var_dump($database->scalar('SELECT COUNT(*) FROM orders'));
$database->beginTransaction();
$database->execute('DELETE FROM orders WHERE sku = ?', ['cake']);
$database->commit();
var_dump($database->scalar('SELECT COUNT(*) FROM orders'));

try {
    $database->query('SELECT * FROM missing');
} catch (RuntimeException $e) {
    echo $e::class, "\n";
}
try {
    new Sharp\Data\Database('nosuchdriver:x');
} catch (RuntimeException $e) {
    echo $e::class, ': ', strtok($e->getMessage(), ':'), "\n";
}

$path = sys_get_temp_dir() . '/sharp-effect-cache-' . getmypid() . '.sqlite';
$cache = new Sharp\Data\Cache($path);
var_dump($cache->get('rate'));
$cache->set('rate', '1.25');
var_dump($cache->get('rate'));
$cache->set('rate', '1.30', Duration::ofSeconds(60));
var_dump((new Sharp\Data\Cache($path))->get('rate'));
$cache->set('stale', 'old', new Duration(-1_000_000_000));
var_dump($cache->get('stale'));
$cache->remove('rate');
$cache->remove('never-set');
var_dump($cache->get('rate'));
$made = 0;
$make = function () use (&$made) { $made++; return "made $made"; };
var_dump($cache->getOrCreate('lazy', $make), $cache->getOrCreate('lazy', $make), $made);
try {
    $cache->get('');
} catch (ValueError $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
unset($cache);
unlink($path);
?>
--EXPECT--
int(0)
int(2)
int(2)
string(5) "array"
array(0) {
}
string(5) "array"
NULL
array(2) {
  [0]=>
  string(3) "tea"
  [1]=>
  string(4) "cake"
}
int(300)
int(750)
NULL
int(2)
int(1)
PDOException
RuntimeException: Database
NULL
string(4) "1.25"
string(4) "1.30"
NULL
NULL
string(6) "made 1"
string(6) "made 1"
int(1)
ValueError: Cache.get: the key is empty
