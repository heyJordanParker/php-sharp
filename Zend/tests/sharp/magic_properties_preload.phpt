--TEST--
A preloaded PHP# class reads the properties a plain PHP class's __get serves, typed or not, and its method of that name where the code takes a function
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/magic_properties_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(MagicProperties\Greeter::class, false));
$greeter = new MagicProperties\Greeter();
$booking = new Lib\Booking();
var_dump($greeter->typed($booking), $greeter->untyped($booking), $greeter->taken($booking), $greeter->called($booking));
?>
--EXPECT--
bool(true)
string(3) "Ada"
string(5) "Grace"
string(17) "the host relation"
string(17) "the host relation"
