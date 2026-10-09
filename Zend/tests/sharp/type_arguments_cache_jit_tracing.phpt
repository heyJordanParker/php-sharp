--TEST--
A new whose type arguments name a type parameter of its class keeps what it spelled for the class of this under the tracing JIT
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
require __DIR__ . '/type_arguments_cache.inc';
?>
--EXPECT--
jit on
App\OrderMaker: App.Order spelled
App\OrderMaker: App.Order cached
App\Maker: int spelled
App\Maker: int cached
App\OrderMaker: App.Order spelled
App\ListMaker: List<int> spelled
App\ListMaker: List<int> spelled
App\OrderMaker: App.Order cached
