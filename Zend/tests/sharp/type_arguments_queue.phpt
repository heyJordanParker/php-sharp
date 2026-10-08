--TEST--
A PHP# object keeps its type arguments through serialize and unserialize, as a queued job does
--FILE--
<?php
require __DIR__ . '/harness/Witness.inc';
require __DIR__ . '/harness/Snapshot.inc';
require __DIR__ . '/harness/Sleeper.inc';
require __DIR__ . '/TypeArguments.sharp';

use App\Queue;

function show(string $serialized): void
{
    echo str_replace("\0", '\0', $serialized), "\n";
}

function arguments(object $object): string
{
    return implode(', ', (new ReflectionObject($object))->getTypeArguments());
}

foreach ([Queue::orders(), Queue::pair(), new App\PaginatedList([]), Queue::page()] as $job) {
    $queued = serialize($job);
    show($queued);
    $run = unserialize($queued);
    echo get_class($run), '<', arguments($run), '> ', var_export($run == $job, true), "\n";
}
?>
--EXPECT--
O:17:"App\PaginatedList":2:{s:5:"items";a:0:{}s:14:"\0<sharp>\0types";s:9:"App.Order";}
App\PaginatedList<App.Order> true
O:8:"App\Pair":3:{s:3:"key";O:9:"App\Order":1:{s:2:"id";i:2;}s:5:"value";i:3;s:14:"\0<sharp>\0types";s:14:"App.Order, int";}
App\Pair<App.Order, int> true
O:17:"App\PaginatedList":2:{s:5:"items";a:0:{}s:14:"\0<sharp>\0types";s:18:"App.DatabaseEntity";}
App\PaginatedList<App.DatabaseEntity> true
O:13:"App\OrderPage":1:{s:5:"items";a:0:{}}
App\OrderPage<> true
