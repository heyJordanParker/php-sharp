--TEST--
Linking a PHP# class autoloads the classes and interfaces its header names
--FILE--
<?php

spl_autoload_register(function (string $class): void {
    echo "autoload $class\n";
    require_once __DIR__ . '/Header.inc';
});

require __DIR__ . '/Header.sharp';

var_dump(get_parent_class(Demo\Page::class), (new Demo\Page())->number());
?>
--EXPECT--
autoload Lib\Named
string(10) "Lib\Entity"
int(7)
