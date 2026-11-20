--TEST--
A result PHP# did not check, from a function value, a PHP# interface a plain PHP class implements, or a collection method that stores a function value's result, is checked where it enters: an int becomes a float, and null or a non-object is refused
--FILE--
<?php
require __DIR__ . '/unverified_results.inc';
?>
--EXPECT--
Function<Any()> giving null: Function<Any()>: Return value must be of type Any, null returned
Function<Any()> giving 1: 1
Function<Object()> giving 5: Function<Object()>: Return value must be of type Object, int returned
Source.read() giving null: Crossed.Source.read(): Return value must be of type Any, null returned
map: array (   0 => 2.0,   1 => 4.0, )
sumOf: 6.0
groupBy giving text: Function<float(int)>: Return value must be of type float, string returned
associateBy giving text: Function<float(int)>: Return value must be of type float, string returned
lazy map: array (   0 => 2.0,   1 => 4.0, )
null-safe map: array (   0 => 2.0,   1 => 4.0, )
map to Any giving null: Function<Any(int)>: Return value must be of type Any, null returned
