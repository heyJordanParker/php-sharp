--TEST--
A PHP# interface property stated `{ get; init; }` runs as without the JIT under the function JIT
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=function
opcache.jit_buffer_size=32M
--SKIPIF--
<?php
if (ini_get('opcache.jit') === false) die('skip JIT is not available');
?>
--FILE--
<?php
echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";
require __DIR__ . '/harness/fixture.inc';
fixture('InterfaceInit');
require __DIR__ . '/interface_init.inc';
?>
--EXPECT--
jit on
set while each object is created, read through the interface: [3,1,4,5]
a copy sets it: [6,7]
plain PHP sees the interface property get-only: false
plain PHP writes it after creation: Cannot modify protected(set) property InterfaceInit\Boxed::$max from global scope
