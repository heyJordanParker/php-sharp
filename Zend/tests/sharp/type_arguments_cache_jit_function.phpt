--TEST--
A new whose type arguments name a type parameter of its class keeps what it spelled for the class of this under the function JIT
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
