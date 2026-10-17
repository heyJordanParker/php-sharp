--TEST--
A preloaded PHP# never method throws to its caller, and is PHP's never to plain PHP
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/never_methods_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(Nevers\Errors::class, false));
echo Nevers\Errors::named('ada'), "\n";
foreach ([fn () => Nevers\Errors::named(null), fn () => Nevers\Errors::counted(true)] as $call) {
    try {
        $call();
    } catch (Nevers\BadInput $e) {
        echo $e->getMessage(), "\n";
    }
}
echo (new ReflectionMethod(Nevers\Errors::class, 'fail'))->getReturnType(), "\n";
?>
--EXPECT--
bool(true)
ada
name is required
bad count
never
