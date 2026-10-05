--TEST--
Preloading links a PHP# class header that opcache_compile_file compiled
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/class_header_preload_compile.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php

foreach ([new Demo\Page(), new Demo\Card()] as $object) {
    $interfaces = class_implements($object);
    ksort($interfaces);
    echo get_parent_class($object), ' ', implode(',', $interfaces), ' ', $object->link(), "\n";
}
?>
--EXPECT--
Lib\Entity Demo\Linkable,Lib\Named /page
Lib\Shelf Demo\Linkable,Lib\Named /card
