--TEST--
Linking a PHP# class autoloads the classes and interfaces its header names
--FILE--
<?php

spl_autoload_register(function (string $class): void {
    echo "autoload $class\n";
    require_once __DIR__ . '/harness/Header.inc';
});

require __DIR__ . '/Header.sharp';

var_dump(get_parent_class(Demo\Article::class), (new Demo\Article())->number());
?>
--EXPECT--
autoload Lib\Named
string(10) "Lib\Record"
int(7)
