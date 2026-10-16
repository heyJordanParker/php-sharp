--TEST--
PHP# internal runs as public: another class of the namespace writes an internal set and calls an internal method, plain PHP reaches them too, and an internal init stays protected(set)
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('Reach');

$shop = new Reach\Shop(5);
echo (new Reach\Till())->run($shop), "\n";

$shop->views = 4;
echo $shop->views, " ", $shop->secret(), "\n";
var_dump((new ReflectionMethod(Reach\Shop::class, 'secret'))->isPublic());

try {
    $shop->id = 1;
} catch (Error $error) {
    echo $error->getMessage(), "\n";
}
?>
--EXPECT--
17
4 7
bool(true)
Cannot modify protected(set) property Reach\Shop::$id from global scope
