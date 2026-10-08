--TEST--
A PHP# object keeps its type arguments through serialize and unserialize, as a queued job does, in the default, __serialize and __sleep forms, and r: and R: after it still name the right value
--FILE--
<?php
require __DIR__ . '/type_arguments.inc';

use App\Queue;

echo "the default form\n";
foreach ([Queue::orders(), Queue::pair(), new App\PaginatedList([]), Queue::page()] as $job) {
    $queued = serialize($job);
    show($queued);
    $run = unserialize($queued);
    echo get_class($run), '<', implode(', ', arguments($run)), '> ', var_export($run == $job, true), "\n";
}

echo "the __serialize form, which __unserialize never sees the type arguments in\n";
// Ledger inherits __serialize and __unserialize from Lib\Snapshot, a PHP class.
$queued = serialize(Queue::ledger());
show($queued);
$run = unserialize($queued);
echo get_class($run), '<', implode(', ', arguments($run)), ">\n";
var_dump(Lib\Snapshot::$restored);

echo "the __sleep form\n";
// Journal inherits __sleep from Lib\Sleeper, which names only entries.
$queued = serialize(Queue::journal());
show($queued);
$run = unserialize($queued);
echo get_class($run), '<', implode(', ', arguments($run)), ">\n";
var_dump($run->entries);

echo "the type arguments take no reference number\n";
$order = new App\Order(7);
$tag = 'kept';
foreach (['default form' => Queue::orders(), '__serialize form' => Queue::ledger(), '__sleep form' => Queue::journal()] as $form => $object) {
    $copy = unserialize(serialize([$object, $object, $order, $order, &$tag, &$tag]));
    echo $form, ': ',
        var_export($copy[0] === $copy[1], true), ' ',
        var_export($copy[2] === $copy[3], true), ' ',
        $copy[2]->id, ' ',
        implode(', ', arguments($copy[0])), "\n";
    $copy[4] = 'changed';
    echo $copy[5], "\n";
}
?>
--EXPECT--
the default form
O:17:"App\PaginatedList":2:{s:5:"items";a:0:{}s:14:"\0<sharp>\0types";s:9:"App.Order";}
App\PaginatedList<App.Order> true
O:8:"App\Pair":3:{s:3:"key";O:9:"App\Order":1:{s:2:"id";i:2;}s:5:"value";i:3;s:14:"\0<sharp>\0types";s:14:"App.Order, int";}
App\Pair<App.Order, int> true
O:17:"App\PaginatedList":2:{s:5:"items";a:0:{}s:14:"\0<sharp>\0types";s:18:"App.DatabaseEntity";}
App\PaginatedList<App.DatabaseEntity> true
O:13:"App\OrderPage":1:{s:5:"items";a:0:{}}
App\OrderPage<> true
the __serialize form, which __unserialize never sees the type arguments in
O:10:"App\Ledger":2:{s:4:"kept";b:1;s:14:"\0<sharp>\0types";s:9:"App.Order";}
App\Ledger<App.Order>
array(1) {
  ["kept"]=>
  bool(true)
}
the __sleep form
O:11:"App\Journal":2:{s:7:"entries";a:0:{}s:14:"\0<sharp>\0types";s:9:"App.Order";}
App\Journal<App.Order>
array(0) {
}
the type arguments take no reference number
default form: true true 7 App.Order
changed
__serialize form: true true 7 App.Order
changed
__sleep form: true true 7 App.Order
changed
