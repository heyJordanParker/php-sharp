--TEST--
Plain PHP's array keyed by int alone arrives as a List wherever it sits: an int-keyed shape, a list shape, and a shape's field, renumbered, a rest pattern reads such a shape and a tuple, and a value that may be a list or a map keeps its string keys
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
an int-keyed shape matches [int a, ...List<int> rest]: 6
a tuple matches [int a, ...List<int> rest]: 6
an arm after one every tuple matches never runs: 5
a shape's array<int, int> field is List<int>: true
a field array_filter left gaps in is List<int>: true, first 7
a constant array<int, int> with gaps arrives renumbered: 5
a constant shape keyed from 1 arrives renumbered: 7
a list or a map keeps its keys: {"x":1,"y":2}
a map of lists or maps keeps each one's keys: [1,2] {"x":1,"y":2}
