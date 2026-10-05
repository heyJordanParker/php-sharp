--TEST--
PHP# Class.y reads a constant, an enum case or else a static property, looked up when it runs
--FILE--
<?php
require __DIR__ . '/class_members.inc';
?>
--EXPECT--
int(10)
enum(Lib\Order::Descending)
string(8) "registry"
int(2)
int(100)
string(7) "changed"
Undefined constant or static property Lib\Registry::missing
Cannot access protected property Lib\Registry::$hidden
