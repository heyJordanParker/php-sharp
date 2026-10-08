--TEST--
A PHP# object keeps its type arguments through the __serialize and __sleep forms, and __unserialize never sees them
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

// Ledger inherits __serialize and __unserialize from Lib\Snapshot, a PHP class.
$queued = serialize(Queue::ledger());
show($queued);
$run = unserialize($queued);
echo get_class($run), '<', arguments($run), ">\n";
var_dump(Lib\Snapshot::$restored);

// Journal inherits __sleep from Lib\Sleeper, which names only entries.
$queued = serialize(Queue::journal());
show($queued);
$run = unserialize($queued);
echo get_class($run), '<', arguments($run), ">\n";
var_dump($run->entries);
?>
--EXPECT--
O:10:"App\Ledger":2:{s:4:"kept";b:1;s:14:"\0<sharp>\0types";s:9:"App.Order";}
App\Ledger<App.Order>
array(1) {
  ["kept"]=>
  bool(true)
}
O:11:"App\Journal":2:{s:7:"entries";a:0:{}s:14:"\0<sharp>\0types";s:9:"App.Order";}
App\Journal<App.Order>
array(0) {
}
