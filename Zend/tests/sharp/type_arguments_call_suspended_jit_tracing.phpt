--TEST--
A generic call whose argument suspends the Fiber gives the method its type arguments when the Fiber resumes, under the tracing JIT
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=tracing
opcache.jit_buffer_size=32M
opcache.jit_hot_func=1
opcache.jit_hot_loop=1
opcache.jit_hot_return=1
opcache.jit_hot_side_exit=1
--FILE--
<?php
echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";
require __DIR__ . '/type_arguments_call_suspended.inc';
?>
--EXPECT--
jit on
suspended
App\Box<App.Order>
suspended
App\Box<App.Order>
