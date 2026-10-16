--TEST--
PHP# Object holds any object, runs as PHP's object, and is narrowed by is
--FILE--
<?php
require __DIR__ . '/object.inc';
?>
--EXPECTF--
bool(true)
bool(true)
bool(true)
T7
other
Holders\Ids::identity(): Argument #1 ($value) must be of type object, int given, called in %s on line %d
Holders\Ids::either(): Argument #2 ($fallback) must be of type object, null given, called in %s on line %d
