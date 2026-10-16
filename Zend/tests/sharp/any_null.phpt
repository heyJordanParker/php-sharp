--TEST--
PHP# Any refuses null from plain PHP, as a parameter, a property, a List element and a Map value, while Any? takes it
--FILE--
<?php
require __DIR__ . '/any_null.inc';
?>
--EXPECTF--
keep 1: 1
keep null: Strict\NonNull::keep(): Argument #1 ($value) must be of type Any, null given, called in %s on line %d
count [1, "a"]: 2
count [1, null]: Strict\NonNull::count(): Argument #1 ($values) must be of type List<Any>, array given: [1] expected Any, got null, called in %s on line %d
size [a: 1]: 1
size [a: null]: Strict\NonNull::size(): Argument #1 ($values) must be of type Map<string, Any>, Sharp.Map given: ["a"] expected Any, got null, called in %s on line %d
loose null: NULL
set 2: 2
set null: Cannot assign null to property Strict\NonNull::$value of type Any
