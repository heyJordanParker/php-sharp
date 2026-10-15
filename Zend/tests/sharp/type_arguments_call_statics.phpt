--TEST--
Each type argument of a generic class keeps its own static fields, which every write changes in place, a subclass shares through its header, and a first use copies from the defaults
--FILE--
<?php
require __DIR__ . '/type_arguments_calls.inc';

use App\Order;
use Calls\Counter;
use Calls\Repository;
use Calls\Shelf;

// Each round writes the statics of type arguments no code used yet, so each first read copies the defaults. A hot
// function's second call runs the code the tracing JIT compiled on its first.
$order = new Order(1);
foreach ([1, 2] as $round) {
    echo implode(', ', $round === 1 ? Repository::orderStatics($order) : Repository::shelfStatics(new Shelf())), "\n";
    echo implode(', ', Repository::inherited($order)), "\n";
    // The PHP a static field lowers to, written with `[]=` and a nested index, which PHP# writes with other forms.
    $key = $round === 1 ? 'App.Order' : 'Calls.Shelf';
    \Sharp\Internal\statics(Counter::class, $key)['items'][] = $order;
    \Sharp\Internal\statics(Counter::class, $key)['grid'][0][1] = 7;
    echo count(\Sharp\Internal\statics(Counter::class, $key)['items']), ', ',
        \Sharp\Internal\statics(Counter::class, $key)['grid'][0][1], ', ',
        \Sharp\Internal\statics(Counter::class, "List<$key>")['grid'][0][1], "\n";
}
?>
--EXPECT--
8, 9, 2, 9, 3, 9, 40, 0
101, 2, 101
5, 7, 0
8, 9, 2, 9, 3, 9, 40, 0
101, 2, 101
3, 7, 0
