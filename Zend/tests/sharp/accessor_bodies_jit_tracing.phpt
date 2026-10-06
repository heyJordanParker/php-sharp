--TEST--
PHP# accessor bodies run as property hooks under the tracing JIT
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
require __DIR__ . '/accessor_bodies.inc';
?>
--EXPECT--
jit on
L-1 Lamp 40 2 light,home,sale 0 5,3
A price is never negative. 40
Cannot modify private(set) property Store\Product::$stock from global scope
Indirect modification of Store\Product::$tags is not allowed
Sofia Sofia, BG
Cannot access protected property Store\Address::$region
Plovdiv Plovdiv true
L-1 Lamp 40 2 light,home,sale 0 5,3
A price is never negative. 40
Cannot modify private(set) property Store\Product::$stock from global scope
Indirect modification of Store\Product::$tags is not allowed
Sofia Sofia, BG
Cannot access protected property Store\Address::$region
Plovdiv Plovdiv true
Store\Shipment::$destination virtual: true
Store\Product::$name virtual: false
Store\Product::$price virtual: false
Store\Product::$stock virtual: false
Store\Product::$tags virtual: false
Store\Product::$rating virtual: false
Store\Address::$city virtual: false
Store\Address::$region virtual: false
