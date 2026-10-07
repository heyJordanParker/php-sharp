--TEST--
A PHP# class uses lambdas, functions held in properties and a method as a value under the function JIT
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
require __DIR__ . '/receipts.inc';
?>
--EXPECT--
jit on
36.00,9.00,2.25 | 4725 | 3 | 1800,450,113 | 14175
36.00,9.00,2.25 | 4725 | 3 | 1800,450,113 | 14175
Call to undefined method Lib\Ledger::scale()
NULL
