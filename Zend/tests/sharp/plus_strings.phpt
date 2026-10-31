--TEST--
PHP# + joins two strings, a string and a class name included, and still adds two numbers
--FILE--
<?php
require __DIR__ . '/plus.inc';
?>
--EXPECT--
string(12) "Ada Lovelace"
string(2) "12"
string(10) "12constant"
string(5) "done!"
string(8) "log: a b"
int(5)
string(6) "ababab"
string(7) "a, b, c"
int(3)
string(16) "Class: Demo\Plus"
string(16) "Class: Demo\Plus"
