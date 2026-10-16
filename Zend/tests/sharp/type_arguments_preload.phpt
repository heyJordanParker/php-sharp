--TEST--
A preloaded generic PHP# class or struct gives its objects their type arguments, runs a static's initial value that runs as code once for each type argument, and new throws an Error when its class name reaches a preloaded class of another arity
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
echo implode(', ', Maker::made()), "\n";
foreach ([Maker::pair(...), Maker::node(...)] as $make) {
    try {
        var_dump($make());
    } catch (Error $error) {
        echo $error::class, ': ', $error->getMessage(), "\n";
    }
}
$pair = Demo\Structs::pair(1, 'a');
echo implode(', ', (new ReflectionObject($pair))->getTypeArguments()), "\n";
echo Demo\Structs::made(), "\n";
var_dump(Demo\Structs::samePairs($pair, Demo\Structs::pair(1, 'a')), Demo\Structs::keys($pair, Demo\Structs::pair(1, 'a')));
try {
    Demo\Structs::parsed(['first' => 'x', 'second' => 'c']);
} catch (Sharp\InvalidFields $error) {
    echo $error->getMessage(), "\n";
}
?>
--EXPECT--
int, string
Any?, Any?
1, 2, 1
Error: Class PlainPair declares no type parameters, so new cannot give it <int, string>
Error: Class Aliased\Twin declares 2 type parameters, so new cannot give it <int>
int, string
int string | string int
bool(true)
int(1)
first: expected int, got string 'x'
