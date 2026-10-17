--TEST--
Two Sets are == when they hold equal elements in any order, also in a List, a Map, a Set and a struct, a List of Sets searches by ==, and an event's Set parameter and a case's Set or Map field hold the object, also when plain PHP passes an array
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('SetValues');
require __DIR__ . '/set_values.inc';
library();

run_rows();
?>
--EXPECT--
[1, 2] and [2, 1] as Set<int>: true
from plain PHP: true
sets of different sizes: true
sets of different elements: true
a nullable set: [true,false,false,true]
a List<Set<int>>: [true,false]
a Map<string, Set<int>>: [true,false]
a Set<Set<int>>: true
contains on a List<Set<int>>: [true,false]
indexOf on a List<Set<int>>: [1,null]
remove on a List<Set<int>>: 2
equal sets hash alike in any order: 2
a struct with a Set field: [true,false,1]
an event with a Set parameter, emitted and heard: ["7 2 vip same"]
an event built in plain PHP: ["8 2 vip same","Sharp\\Set"]
a case with a Set field, built and matched: ["marked 2 same","marked 2 same","Sharp\\Set",true]
a case with a Map field, built in plain PHP: ["counted 3","Sharp\\Map"]
