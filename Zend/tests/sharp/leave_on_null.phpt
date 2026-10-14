--TEST--
PHP# `a ?? return`, `a ?? continue` and `a ?? break` leave from the middle of an expression when a is null
--FILE--
<?php
require __DIR__ . '/leave_on_null.inc';
?>
--EXPECT--
int(10)
int(-1)
int(2)
string(12) "<pro> is Pro"
string(18) "<basic> is missing"
float(2)
NULL
int(8)
int(6)
int(5)
int(10)
int(-2)
int(10)
int(2)
int(-3)
