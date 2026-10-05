--TEST--
PHP# Class.y reads a constant, an enum case or else a static property under the tracing JIT
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=tracing
opcache.jit_buffer_size=32M
opcache.jit_hot_func=1
opcache.jit_hot_loop=1
opcache.jit_hot_return=1
opcache.jit_hot_side_exit=1
--FILE--
<?php
echo opcache_get_status()['jit']['on'] ? "jit on\n" : "jit off\n";
require __DIR__ . '/class_members.inc';
?>
--EXPECT--
jit on
int(10)
enum(Lib\Order::Descending)
string(8) "registry"
int(2)
int(100)
int(3)
enum(Lib\Order::Ascending)
enum(Lib\Order::Descending)
string(7) "changed"
Undefined constant or static property Lib\Registry::missing
Cannot access protected property Lib\Registry::$hidden
