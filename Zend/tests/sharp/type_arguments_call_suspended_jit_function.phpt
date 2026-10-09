--TEST--
A generic call whose argument suspends the Fiber gives the method its type arguments when the Fiber resumes, under the function JIT
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
require __DIR__ . '/type_arguments_call_suspended.inc';
?>
--EXPECT--
jit on
suspended
App\Box<App.Order>
suspended
App\Box<App.Order>
