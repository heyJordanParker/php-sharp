--TEST--
A preloaded generic class's new TItem.fromJson(…) calls the named constructor of its type argument, as a request with no preloading does
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.preload={PWD}/named_through_type_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(NamedThroughType\Repository::class, false));
require __DIR__ . '/harness/fixture.inc';
fixture('NamedThroughType');
require __DIR__ . '/named_through_type.inc';
?>
--EXPECT--
bool(true)
new TItem.fromJson for Repository<Invoice> and Repository<Record>: invoice invoice 7, record record 8
plain PHP calls the named constructor it runs as: NamedThroughType\Invoice invoice 9
new TItem.fromJson for Repository<Invoice> and Repository<Record>: invoice invoice 7, record record 8
plain PHP calls the named constructor it runs as: NamedThroughType\Invoice invoice 9
