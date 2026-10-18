--TEST--
A preloaded PHP# class compares Sets by their elements in any order, and its event's, case's and struct's Set, nested or not, holds the object, as a request with no preloading does
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/set_values_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
require __DIR__ . '/set_values.inc';
library();

var_dump(class_exists(SetValues\Report::class, false));
run_rows();
?>
--EXPECTF--
bool(true)
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
an event built in plain PHP: ["8 2 vip same",true,["vip","new"]]
a case with a Set field, built and matched: ["marked 2 same","marked 2 same",true,["new","vip"],true]
a case with a Map field, built in plain PHP: ["counted 3",true,{"vip":3}]
an event checks the elements plain PHP passes: TypeError: SetValues\Tagged::__construct(): Argument #1 ($tags) must be of type Set<string>, array given: [0] expected string, got int 1, called in %s on line %d
a case checks the elements plain PHP passes: TypeError: SetValues\Badge\Marked::__construct(): Argument #1 ($tags) must be of type Set<string>, array given: [0] expected string, got int 1, called in %s on line %d
an event with a List<Set<int>>, built in plain PHP and in PHP#: [true,[[1,2]],"1 true true","1 true true"]
a case with a List<Set<int>>, built in plain PHP: [true,[[1,2]],"nested 1 true",true]
a struct whose only collection is a List<Set<int>>: [true,[[1,2]],true,2]
a list literal where a Set is expected, at any depth: [[true,false,true],1,true,true]
a Set<Set<int|string>>: 22
a union that holds a collection: [[[true,false,true,false,true],[false,true,true,true,false]],[true,false]]
