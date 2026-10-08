--TEST--
PHP# accessor bodies run as property hooks: field and value, a validating set, a private set, a collection written back through set, an auto accessor beside a body, a promoted property and a property over a plain PHP base
--FILE--
<?php
require __DIR__ . '/accessor_bodies.inc';
?>
--EXPECT--
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
Store\Parcel::$destination virtual: true
Store\Product::$name virtual: false
Store\Product::$price virtual: false
Store\Product::$stock virtual: false
Store\Product::$tags virtual: false
Store\Product::$rating virtual: false
Store\Address::$city virtual: false
Store\Address::$region virtual: false
