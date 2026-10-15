--TEST--
PHP# lazy keeps the value of its first read, even when its inputs change, keeps nothing when the body throws, keeps null for a nullable one, runs a body with an effect once, and a static one keeps its value for a singleton's lifetime
--FILE--
<?php
require __DIR__ . '/Lazy.sharp';

$plan = new Lazy\Plan();
$plan->setId(3);
echo $plan->doubled, "\n";
$plan->setId(4);
echo $plan->doubled, "\n";

$plan = new Lazy\Plan();
try {
    echo $plan->remainder, "\n";
} catch (DivisionByZeroError $error) {
    echo $error->getMessage(), "\n";
}
$plan->setId(7);
echo $plan->remainder, "\n";
$plan->setId(9);
echo $plan->remainder, "\n";

$plan = new Lazy\Plan();
var_dump($plan->found);
$plan->setId(7);
var_dump($plan->found);

try {
    $plan->doubled = 1;
} catch (Error $error) {
    echo $error->getMessage(), "\n";
}

// The body changes `Loads`' count, an effect, and the second read keeps the first value without running it again.
$plan = new Lazy\Plan();
echo $plan->stamp, ' ', $plan->stamp, ' ', Lazy\Loads::seen(), "\n";

// A copy is a new value, so it keeps no `lazy` value: `with` on a struct, and plain PHP's `clone` of an object.
echo Lazy\Square::grown(), "\n";
$plan = new Lazy\Plan();
$plan->setId(3);
echo $plan->doubled, ' ';
$copy = clone $plan;
$copy->setId(4);
echo $copy->doubled, ' ', $plan->doubled, "\n";

// A `static lazy` body runs once for a singleton's lifetime, and a throw keeps nothing.
echo Lazy\Loads::firstTwice(), ' ', Lazy\Loads::seen(), "\n";
try {
    echo Lazy\Loads::ratioNow(), "\n";
} catch (DivisionByZeroError $error) {
    echo $error->getMessage(), "\n";
}
Lazy\Loads::divideBy(7);
echo Lazy\Loads::ratioNow(), "\n";
Lazy\Loads::divideBy(9);
echo Lazy\Loads::ratioNow(), "\n";

// A test gets fresh singletons, so a `static lazy` computes again once a test ends and the next one starts: `ratio`
// is 100 % 9 now, not the 2 it kept, and `first` takes the third `next()`.
\Sharp\Internal\endTest();
\Sharp\Internal\startTest(Lazy\Loads::class);
echo Lazy\Loads::ratioNow(), ' ', Lazy\Loads::firstTwice(), "\n";
?>
--EXPECT--
6
6
Modulo by zero
2
2
NULL
NULL
Cannot modify private(set) property Lazy\Plan::$doubled from global scope
1 1 1
9 16
6 8 6
4 2
Modulo by zero
2
2
1 6
