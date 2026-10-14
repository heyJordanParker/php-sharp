--TEST--
PHP# lazy keeps the value of its first read, even when its inputs change, keeps nothing when the body throws, and keeps null for a nullable one
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
