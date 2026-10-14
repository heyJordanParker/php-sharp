--TEST--
A lambda bound to an object of another class has no type arguments of the class it is written in to read
--FILE--
<?php
require __DIR__ . '/type_arguments_calls.inc';

use App\Order;
use Calls\Repository;

// A hot function's second call runs the code the tracing JIT compiled on its first.
foreach ([1, 2] as $round) {
    $inspection = Repository::orderInspection();
    foreach ([$inspection->tester(), $inspection->namer(), $inspection->maker()] as $lambda) {
        try {
            Closure::bind($lambda, new Order(1), Order::class)(new Order(1));
        } catch (Error $error) {
            echo $error->getMessage(), "\n";
        }
    }
}
?>
--EXPECT--
Cannot read the type arguments of the class a lambda is written in from an object of another class
Cannot read the type arguments of the class a lambda is written in from an object of another class
Cannot read the type arguments of the class a lambda is written in from an object of another class
Cannot read the type arguments of the class a lambda is written in from an object of another class
Cannot read the type arguments of the class a lambda is written in from an object of another class
Cannot read the type arguments of the class a lambda is written in from an object of another class
