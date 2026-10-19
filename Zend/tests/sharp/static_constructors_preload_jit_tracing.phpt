--TEST--
Preloaded PHP# static constructors run as a request with no preloading runs them under the tracing JIT
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/static_constructors_preload.inc
opcache.jit=tracing
opcache.jit_buffer_size=32M
opcache.jit_hot_func=1
opcache.jit_hot_loop=1
opcache.jit_hot_return=1
opcache.jit_hot_side_exit=1
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
if (ini_get('opcache.jit') === false) die('skip JIT is not available');
?>
--FILE--
<?php
echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";
var_dump(class_exists(StaticConstructors\Rates::class, false));
require __DIR__ . '/static_constructors.inc';
?>
--EXPECT--
jit on
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
new, a computed constant and a field default run no static constructor: [4,5,4,["pool"]]
the first use of a static runs it: [0,["ticket"]]
a derived class runs its base class's static constructor first: RuntimeException: broken
in that order: ["base","broken"]
use 1 of an inherited static after the static constructor threw: Error: The static constructor of StaticConstructors.Broken threw RuntimeException: broken (previous RuntimeException: broken)
use 2 of an inherited static after the static constructor threw: Error: The static constructor of StaticConstructors.Broken threw RuntimeException: broken (previous RuntimeException: broken)
the base class keeps its own statics: 1
