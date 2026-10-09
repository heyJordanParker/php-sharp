--TEST--
A generic call gives the method the type arguments it writes or the checker infers, static or not and through a spread, under the tracing JIT
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
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Box<App.Order>
App\Pair<int, App.Order>
App\Pair<int, int>
