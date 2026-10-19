--TEST--
Plain PHP's arrays keyed by int alone arrive as a preloaded PHP# reader's Lists as in a request with no preloading, a constant one the optimizer folds into the reader included
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/plain_shaped_lists_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(Demo\Pivot::class, false), class_exists(Demo\ShapedLists::class, false));
echo implode("\n", Demo\ShapedLists::read()), "\n";
?>
--EXPECT--
bool(true)
bool(true)
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
