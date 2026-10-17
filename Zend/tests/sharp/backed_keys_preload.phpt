--TEST--
A preloaded PHP# class passes a Map keyed by a backed enum to a plain PHP array parameter with each case's backing value as its key
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/backed_keys_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(BackedKeys\Roll::class, false));
$roll = new BackedKeys\Roll();
echo $roll->described(), ' ', $roll->sized(), ' ', $roll->spread(), "\n";
?>
--EXPECT--
bool(true)
{"active":2,"closed":1} {"1":"s","2":"l"} {"active":1};{"closed":2}
