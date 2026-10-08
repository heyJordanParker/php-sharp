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
int(3)
enum(Lib\Order::Ascending)
enum(Lib\Order::Descending)
string(7) "changed"
int(1)
Typed static property Lib\Registry::$late must not be accessed before initialization
