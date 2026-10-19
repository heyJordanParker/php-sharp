--TEST--
Plain PHP's array keyed by int alone arrives as a List wherever it sits: an int-keyed shape, a list shape, and a shape's field, renumbered
--FILE--
<?php
require __DIR__ . '/harness/Pivot.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('ShapedLists');

echo implode("\n", Demo\ShapedLists::read()), "\n";
?>
--EXPECT--
an int-keyed shape is List<int>: true
an int-keyed shape matches [int a, int b]: 11
a list shape matches [int a, int b]: 15
a shape's array<int, int> field is List<int>: true
a field array_filter left gaps in is List<int>: true, first 7
