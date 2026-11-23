--TEST--
PHP# sets each static whose initial value isn't constant in every request, also for a class opcache preloads, whose preload loads the standard library through the Composer autoloader, as a program does, spec section 27
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.preload={PWD}/preloaded.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
require __DIR__ . '/.sharp/autoload.php';

// Preloading declared the class before this request, which never ran the file that declares it.
var_dump(class_exists(Preloaded\Registry::class, false));
echo Preloaded\Registry::total(), ' ', Preloaded\Registry::$label, "\n";
?>
--EXPECT--
bool(true)
7 READY
