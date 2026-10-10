--TEST--
A generic call gives the method the type arguments it writes or the checker infers, static or not, through a spread and across a Fiber suspension, under the function JIT
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
require __DIR__ . '/type_arguments_call.inc';
?>
--EXPECT--
jit on
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Pair<int, App.Order>
App\Pair<int, int>
suspended
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Pair<int, App.Order>
App\Pair<int, int>
suspended
App\Box<App.Order>
