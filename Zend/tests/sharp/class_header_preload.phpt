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

$page = new Demo\Page();
$interfaces = class_implements($page);
ksort($interfaces);
var_dump(get_parent_class($page), array_keys($interfaces), $page->number());
?>
--EXPECT--
string(10) "Lib\Entity"
array(2) {
  [0]=>
  string(13) "Demo\Linkable"
  [1]=>
  string(9) "Lib\Named"
}
int(7)
