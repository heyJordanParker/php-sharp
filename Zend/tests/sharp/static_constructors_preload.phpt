--TEST--
Preloaded PHP# static constructors run as a request with no preloading runs them, once in the request
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/static_constructors_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(StaticConstructors\Rates::class, false));
require __DIR__ . '/static_constructors.inc';
?>
--EXPECT--
bool(true)
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
