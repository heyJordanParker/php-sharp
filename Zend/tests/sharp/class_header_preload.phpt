--TEST--
Preloading links a PHP# class header
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/class_header_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php

foreach ([new Demo\Article(), new Demo\Card()] as $object) {
    $interfaces = class_implements($object);
    ksort($interfaces);
    echo get_parent_class($object), ' ', implode(',', $interfaces), ' ', $object->link(), "\n";
}
?>
--EXPECT--
Lib\Record Demo\Linkable,Lib\Named /article
Lib\Shelf Demo\Linkable,Lib\Named /card
