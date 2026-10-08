--TEST--
A PHP# class overrides a PHP# parent's override under the tracing JIT, untyped over an untyped root and typed over a typed one
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
require __DIR__ . '/property_override_chain.inc';
?>
--EXPECT--
jit on
'rush_orders fills ' | rush_orders
'rush_orders' | rush_orders
'rush_orders fills ' | rush_orders
'rush_orders' | rush_orders
Lib\Model hasType: false
Rush\Order hasType: false
Rush\RushOrder hasType: false
Lib\TypedModel hasType: true (string)
Typed\Order hasType: true (string)
Typed\RushOrder hasType: true (string)
