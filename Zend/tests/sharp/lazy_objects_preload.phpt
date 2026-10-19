--TEST--
A lazy ghost and a lazy proxy of a preloaded PHP# class whose collections have initial values run as in a request with no preloading
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/LazyObjects/Holder.sharp
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(LazyObjects\Holder::class, false));
require __DIR__ . '/lazy_objects.inc';
?>
--EXPECT--
bool(true)
a lazy ghost reads its collections: [[1,2],{"a":1},["x"]]
a lazy proxy reads its collections: [[1,2],{"a":1},["x"]]
isInitialized() of items after getValue() on a lazy proxy: false, false
isInitialized() of items after getRawValue() on a lazy proxy: false, false
isInitialized() of counts after getValue() on a lazy proxy: false, false
isInitialized() of counts after getRawValue() on a lazy proxy: false, false
isInitialized() of tags after getValue() on a lazy proxy: false, false
isInitialized() of tags after getRawValue() on a lazy proxy: false, false
