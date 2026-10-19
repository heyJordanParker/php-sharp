--TEST--
A PHP# interface states a property `{ get; init; }`, a class, a struct and an event give it, each sets it while its object is created, the interface reads it, and plain PHP sees it get-only and can't write it after creation
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('InterfaceInit');
require __DIR__ . '/interface_init.inc';
?>
--EXPECT--
set while each object is created, read through the interface: [3,1,4,5]
a copy sets it: [6,7]
plain PHP sees the interface property get-only: false
plain PHP writes it after creation: Cannot modify protected(set) property InterfaceInit\Boxed::$max from global scope
