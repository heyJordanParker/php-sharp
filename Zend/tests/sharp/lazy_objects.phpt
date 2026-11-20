--TEST--
A lazy proxy of a PHP# class whose collections have initial values initializes and reads them under opcache, and a lazy ghost, which no constructor builds, throws, and reflection finds a collection property of an initialized proxy as it finds a plain array property
--INI--
opcache.enable=1
opcache.enable_cli=1
--EXTENSIONS--
opcache
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/lazy_objects.inc';
?>
--EXPECT--
a lazy ghost, which no constructor builds: LazyObjects.Holder is built only through its constructor, which this creation doesn't run
a lazy proxy reads its collections: [[1,2],{"a":1},["x"]]
isInitialized() of items after getValue() on a lazy proxy: false, false
isInitialized() of items after getRawValue() on a lazy proxy: false, false
isInitialized() of counts after getValue() on a lazy proxy: false, false
isInitialized() of counts after getRawValue() on a lazy proxy: false, false
isInitialized() of tags after getValue() on a lazy proxy: false, false
isInitialized() of tags after getRawValue() on a lazy proxy: false, false
