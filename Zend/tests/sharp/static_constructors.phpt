--TEST--
A PHP# static constructor runs once, right after the statics' initial values, at the first use of a static, and a throw there makes every later use of the class's statics throw an Error that names the class and the failure
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('StaticConstructors');
require __DIR__ . '/static_constructors.inc';
?>
--EXPECT--
a static method that uses no static: "hello"
no static is used yet, so nothing ran: []
the first use of a static: [92,100,100]
initial values, then the static constructor: ["initial value","static constructor"]
it ran once: [92,1]
setup that fills no static: [0,["initial value","static constructor","registered"]]
use 1 of a class whose static constructor threw: RuntimeException: rates are down
use 2 of a class whose static constructor threw: Error: The static constructor of StaticConstructors.Flaky threw RuntimeException: rates are down (previous RuntimeException: rates are down)
use 3 of a class whose static constructor threw: Error: The static constructor of StaticConstructors.Flaky threw RuntimeException: rates are down (previous RuntimeException: rates are down)
a write after it threw: Error: The static constructor of StaticConstructors.Flaky threw RuntimeException: rates are down (previous RuntimeException: rates are down)
each type argument runs it once: [1,1,1,["initial value","static constructor","registered","pool","pool"]]
a type argument whose static constructor threw: RuntimeException: pool is full
a later use of it: Error: The static constructor of StaticConstructors.Pool<float> threw RuntimeException: pool is full (previous RuntimeException: pool is full)
another type argument still runs: 1
