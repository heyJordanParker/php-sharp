--TEST--
PHP# union, promoted, variadic, spread and named-argument calls run hot under the function JIT
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
require __DIR__ . '/signatures_jit.inc';
echo 'ADD run by the VM: ', opcache_get_status()['jit']['sharp_operator_vm_calls']['ZEND_ADD'] ?? 0, "\n";
echo 'ASSIGN_OP run by the VM: ', opcache_get_status()['jit']['sharp_operator_vm_calls']['ZEND_ASSIGN_OP'] ?? 0, "\n";
?>
--EXPECTF--
jit on
int(6)
int(104)
int(19)
int(203)
int(9)
int(104)
string(4) "none"
int(7)
string(5) "seven"
bool(true)
bool(true)
int(202)
Demo\Signatures::sum(): Argument #2 must be of type int, string given, called in %ssignatures_jit.inc on line 27
Demo\Signatures::label(): Argument #1 ($id) must be of type string|int, array given, called in %ssignatures_jit.inc on line 33
Demo\Signatures::isSame(): Argument #1 ($other) must be of type Demo\Signatures|int, string given, called in %ssignatures_jit.inc on line 39
Error: Named parameter $factor overwrites previous argument
ADD run by the VM: 0
ASSIGN_OP run by the VM: 0
