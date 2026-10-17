--TEST--
A preloaded PHP# class reads a plain PHP class's property by its name, and its method of that name where the code takes a function the property's type does not fit
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/shared_names_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(SharedNames\Labels::class, false));
$labels = new SharedNames\Labels();
$gadget = new Lib\Gadget();
var_dump($labels->read($gadget), $labels->typed($gadget), $labels->maybe($gadget), $labels->maybe(null));
echo implode(',', $labels->mapped($gadget)), "\n";
?>
--EXPECT--
bool(true)
string(6) "gadget"
string(8) "c gadget"
string(8) "d gadget"
NULL
a gadget,b gadget
