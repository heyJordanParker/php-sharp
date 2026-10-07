--TEST--
A PHP# class overrides a plain PHP parent's properties under the function JIT, which never trusts a type the class drops when it links
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=function
opcache.jit_buffer_size=32M
--FILE--
<?php
echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";
require __DIR__ . '/property_override.inc';
?>
--EXPECT--
jit on
orders fills number,total | orders | 20 | false
orders fills number,total | orders | 20 | false
table: untyped, protected, 1 Override
fillable: untyped, protected, 1 Override
with: untyped, protected, 1 Override
timestamps: untyped, public, 1 Override
perPage: int, protected, 1 Override
int(5)
Store\Order::tableName(): Return value must be of type string, int returned
Cannot assign string to property Store\Order::$perPage of type int
