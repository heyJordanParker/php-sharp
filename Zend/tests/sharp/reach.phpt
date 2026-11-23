--TEST--
PHP# internal: another class of the namespace writes an internal set and calls an internal method, while plain PHP outside the namespace can't write the internal set or call the internal method, which PHP still gives as public, and an internal init stays protected(set)
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

$shop = new Reach\Shop(5);
echo (new Reach\Till())->run($shop), "\n";

try {
    $shop->views = 4;
} catch (Error $error) {
    echo $error->getMessage(), "\n";
}
echo $shop->views, "\n";
var_dump((new ReflectionMethod(Reach\Shop::class, 'secret'))->isPublic());
try {
    $shop->secret();
} catch (Error $error) {
    echo $error->getMessage(), "\n";
}

try {
    $shop->id = 1;
} catch (Error $error) {
    echo $error->getMessage(), "\n";
}
?>
--EXPECT--
17
Cannot modify internal(set) property Reach\Shop::$views from global scope, outside namespace Reach
2
bool(true)
Call to internal method Reach\Shop::secret() from global scope, outside namespace Reach
Cannot modify protected(set) property Reach\Shop::$id from global scope
