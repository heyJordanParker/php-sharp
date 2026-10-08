--TEST--
foreach over a PHP# object never reaches its type arguments, by value or by reference
--FILE--
<?php
require __DIR__ . '/harness/Witness.inc';
require __DIR__ . '/harness/Snapshot.inc';
require __DIR__ . '/harness/Sleeper.inc';
require __DIR__ . '/TypeArguments.sharp';

$pair = App\Queue::pair();
foreach ($pair as $name => $value) {
    echo $name, "\n";
}
foreach (new App\PaginatedList([]) as $name => $value) {
    echo $name, "\n";
}
?>
--EXPECT--
key
value
items
