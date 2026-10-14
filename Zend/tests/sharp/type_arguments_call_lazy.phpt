--TEST--
A generic method whose type texts never name its class's type parameters, called from plain PHP on a lazy proxy, leaves the proxy uninitialized
--FILE--
<?php
require __DIR__ . '/type_arguments.inc';

use App\Order;
use App\PaginatedList;
use App\Queue;

$class = new ReflectionClass(PaginatedList::class);
$proxy = $class->newLazyProxy(static function (): PaginatedList {
    echo "initialized\n";

    return Queue::orders();
});

echo get_class($proxy->boxed(new Order(1))), "\n";
var_dump($class->isUninitializedLazyObject($proxy));
?>
--EXPECT--
App\Box
bool(true)
