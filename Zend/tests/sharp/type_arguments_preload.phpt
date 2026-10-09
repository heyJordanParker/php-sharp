--TEST--
A preloaded generic PHP# class gives its objects their type arguments, and new throws an Error when its class name reaches a preloaded class of another arity
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/type_arguments_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
use Aliased\Maker;
use Aliased\Twin;

echo implode(', ', (new ReflectionObject(Maker::twin()))->getTypeArguments()), "\n";
echo implode(', ', (new ReflectionObject(new Twin()))->getTypeArguments()), "\n";
foreach ([Maker::pair(...), Maker::node(...)] as $make) {
    try {
        var_dump($make());
    } catch (Error $error) {
        echo $error::class, ': ', $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
int, string
Any?, Any?
Error: Class PlainPair declares no type parameters, so new cannot give it <int, string>
Error: Class Aliased\Twin declares 2 type parameters, so new cannot give it <int>
