--TEST--
A preloaded PHP# interface property stated `{ get; init; }` runs as in a request with no preloading under the tracing JIT
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/interface_init_preload.inc
opcache.jit=tracing
opcache.jit_buffer_size=32M
opcache.jit_hot_func=1
opcache.jit_hot_loop=1
opcache.jit_hot_return=1
opcache.jit_hot_side_exit=1
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
if (ini_get('opcache.jit') === false) die('skip JIT is not available');
?>
--FILE--
<?php
echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";
var_dump(interface_exists(InterfaceInit\Sized::class, false), class_exists(InterfaceInit\Report::class, false));
require __DIR__ . '/interface_init.inc';
?>
--EXPECT--
jit on
bool(true)
bool(true)
set while each object is created, read through the interface: [3,1,4,5]
a copy sets it: [6,7]
plain PHP sees the interface property get-only: false
plain PHP writes it after creation: Cannot modify protected(set) property InterfaceInit\Boxed::$max from global scope
