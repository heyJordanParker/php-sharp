--TEST--
PHP# `is`, `is not`, `as` and `match` test, narrow and dispatch on a value's type, value and properties
--FILE--
<?php
require __DIR__ . '/pattern_matching.inc';
?>
--EXPECT--
float(12)
float(9)
float(4)
float(0)
float(5)
float(0)
string(7) "nothing"
string(10) "big circle"
string(6) "circle"
string(6) "square"
string(7) "invalid"
string(3) "top"
string(4) "pass"
string(4) "fail"
int(1)
int(2)
int(0)
int(0)
bool(true)
bool(false)
string(5) "first"
string(4) "open"
string(4) "done"
int(2)
int(0)
