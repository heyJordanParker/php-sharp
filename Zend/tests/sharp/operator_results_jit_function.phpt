--TEST--
PHP# operators and sortedBy keep their PHP# results under the function JIT
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
require __DIR__ . '/operator_results.inc';
?>
--EXPECT--
jit on
bool(false)
bool(false)
bool(true)
bool(true)
bool(true)
ArithmeticError: Bit shift by negative number in Operators.sharp on line 165
int(0)
int(8)
int(12)
int(5)
int(2)
string(7) "800 EUR"
string(7) "200 EUR"
string(8) "1500 EUR"
string(7) "166 EUR"
string(5) "2 EUR"
string(10) "250000 EUR"
string(8) "-500 EUR"
bool(true)
int(1)
string(5) "1 EUR"
bool(true)
bool(true)
bool(false)
int(2)
bool(true)
bool(false)
bool(true)
bool(false)
bool(true)
int(0)
string(5) "c a b"
string(16) "10 9 Zebra apple"
int(20)
int(1)
string(7) "450 EUR"
int(2)
int(2)
int(4)
Billing\Ungraded: a negative grade has no order in Operators.sharp on line 360
string(5) "1 2 3"
