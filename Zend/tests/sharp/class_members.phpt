--TEST--
PHP# Class.y reads a constant, an enum case or else a static property, looked up when it runs
--FILE--
<?php

namespace Lib;

require __DIR__ . '/harness/Registry.inc';
require __DIR__ . '/vendor/autoload.php';

use Demo\Members;

for ($i = 0; $i < 100; $i++) {
    $start = Members::start();
    $order = Members::order();
    $label = Members::label();
    $version = Members::version();
    $count = Members::bump();
}

var_dump($start, $order, $label, $version, $count);
var_dump(Members::NEXT, Members::$sorted, Members::pick());

Registry::$label = 'changed';
var_dump(Members::label());

// A loop over a static property array reads each element's value, even one a reference shares.
$kept = &Registry::$items[0];
for ($i = 0; $i < 50; $i++) {
    $ones = Members::ones();
}
var_dump($ones);

try {
    Members::late();
} catch (\Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
int(10)
enum(Lib\Order::Descending)
string(8) "registry"
int(2)
int(100)
int(3)
enum(Lib\Order::Ascending)
enum(Lib\Order::Descending)
string(7) "changed"
int(1)
Typed static property Lib\Registry::$late must not be accessed before initialization
