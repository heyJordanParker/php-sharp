--TEST--
PHP# + joins two strings, and still adds two numbers
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
int(3)
