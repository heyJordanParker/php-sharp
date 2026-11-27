--TEST--
PHP# typeof(A).isAssignableTo(typeof(B)) answers whether a value of A fits B, as `is` answers it for such a value: a class to its interface and back, a list to Iterable, a type to its nullable type and back, a union to a wider union, and a generic class by its type argument's variance
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

$words = fn (array $answers): string => implode(' ', array_map(fn (bool $answer): string => $answer ? 'true' : 'false', $answers));
$assignable = AssignableTypes\Checks::assignable();
$tested = AssignableTypes\Checks::tested();
echo $words(array_slice($assignable, 0, 5)), "\n";
echo $words(array_slice($assignable, 5)), "\n";
echo $words($tested), "\n";
var_dump($assignable === $tested);
?>
--EXPECT--
true false true true false
true false
true false true true false true false
bool(true)
