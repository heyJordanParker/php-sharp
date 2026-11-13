--TEST--
Plain PHP names a class in any case, so opcache preloads a child whose `baz` in a signature is its parent's `Baz`
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/case_variance_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(ChildWithBaz::class, false));
var_dump(get_class((new ChildWithBaz())->take(new Baz())));
?>
--EXPECT--
bool(true)
string(3) "Baz"
