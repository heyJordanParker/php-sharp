--TEST--
A value of a type parameter has the extensions of its bound: size() on a THeap : Heap<string> runs the extension of Heap<TItem>, as it does on a Heap<string>
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('BoundExtensions');

$heap = BoundExtensions\Report::made();

var_dump(BoundExtensions\Report::bounded($heap), BoundExtensions\Report::direct($heap));
?>
--EXPECT--
int(2)
int(2)
