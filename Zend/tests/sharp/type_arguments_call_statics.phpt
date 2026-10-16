--TEST--
Each type argument of a generic class keeps its own static fields, which every write changes in place, a subclass shares through its header, and a first use copies from the defaults and runs each initial value that runs as code once, and its own accessors and static lazy values
--FILE--
<?php
require __DIR__ . '/type_arguments_calls.inc';

use App\Order;
use Calls\Counter;
use Calls\Repository;
use Calls\Runs;
use Calls\Shelf;
use Calls\Tally;

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

// `made` and `once` run once for each type argument, the bounds' among them, which a call from plain PHP reads with,
// and the log shows each run. `broken` throws on the first use of `Fragile<Order>`, which leaves `count` as it is and
// runs nothing again.
foreach ([1, 2] as $round) {
    echo implode(' | ', Repository::orderTallied()), "\n";
    echo implode(' | ', Repository::sharedTallied()), "\n";
    echo implode(' | ', Repository::talliedThrough()), "\n";
    echo Tally::bump(), "\n";
    try {
        echo Repository::fragile(), "\n";
    } catch (RuntimeException $error) {
        echo $error::class, ': ', $error->getMessage(), "\n";
    }
    echo Repository::fragile(), "\n";
    echo implode(', ', Runs::logged()), "\n";
}
// A class that isn't generic runs an initial value that reads another static as code too.
echo Runs::$second, "\n";
?>
--EXPECT--
8, 9, 2, 9, 3, 9, 40, 0
101, 2, 101
5, 7, 0
8, 9, 2, 9, 3, 9, 40, 0
101, 2, 101
3, 7, 0
made App\Order | label App\Order 0 | next 3 | 22 4 2 | once App\Order | label App\Order 2
made App\SharedOrder | label App\SharedOrder 0 | next 3 | 22 4 2 | once App\SharedOrder | label App\SharedOrder 2
100 4 | label App\SharedOrder 2 | 22
10
RuntimeException: broken
3
made App\Order, once App\Order, made App\SharedOrder, once App\SharedOrder, made App\DatabaseEntity, broken
made App\Order | label App\Order 2 | next 3 | 22 4 4 | once App\Order | label App\Order 4
made App\SharedOrder | label App\SharedOrder 2 | next 3 | 22 4 4 | once App\SharedOrder | label App\SharedOrder 4
100 4 | label App\SharedOrder 4 | 22
20
3
3
made App\Order, once App\Order, made App\SharedOrder, once App\SharedOrder, made App\DatabaseEntity, broken
2
