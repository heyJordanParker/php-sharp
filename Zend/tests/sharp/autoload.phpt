--TEST--
PHP# autoloads a .sharp class
--FILE--
<?php

spl_autoload_register(function (string $class): void {
    require __DIR__ . '/' . substr($class, strlen('Demo\\')) . '.sharp';
});

echo (new Demo\Calc)->run(1), "\n";
?>
--EXPECT--
22
