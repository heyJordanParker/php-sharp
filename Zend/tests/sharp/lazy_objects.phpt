--TEST--
A lazy ghost and a lazy proxy of a PHP# class whose collections have initial values initialize and read them under opcache, and reflection finds a collection property of an initialized proxy as it finds a plain array property
--INI--
opcache.enable=1
opcache.enable_cli=1
--EXTENSIONS--
opcache
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('LazyObjects');
require __DIR__ . '/lazy_objects.inc';
?>
--EXPECT--
a lazy ghost reads its collections: [[1,2],{"a":1},["x"]]
a lazy proxy reads its collections: [[1,2],{"a":1},["x"]]
isInitialized() of items after getValue() on a lazy proxy: false, false
isInitialized() of items after getRawValue() on a lazy proxy: false, false
isInitialized() of counts after getValue() on a lazy proxy: false, false
isInitialized() of counts after getRawValue() on a lazy proxy: false, false
isInitialized() of tags after getValue() on a lazy proxy: false, false
isInitialized() of tags after getRawValue() on a lazy proxy: false, false
