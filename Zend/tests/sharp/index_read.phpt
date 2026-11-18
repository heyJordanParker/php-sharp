--TEST--
A bare PHP# index read throws OutOfRangeException on a missing key, and ?? and ?. read it as null
--FILE--
<?php

use Lib\Slots;

require __DIR__ . '/attempt.inc';
require __DIR__ . '/harness/Slots.inc';
require __DIR__ . '/vendor/autoload.php';

for ($round = 0; $round < 100; $round++) {
    ob_start();
    attempt(fn () => Demo\IndexRead::at([10, 20], 1));
    attempt(fn () => Demo\IndexRead::at([10, 20], 2));
    attempt(fn () => Demo\IndexRead::at([10, 20], -1));
    attempt(fn () => Demo\IndexRead::nested([[1, 2]], 0, 1));
    attempt(fn () => Demo\IndexRead::nested([[1, 2]], 0, 2));
    attempt(fn () => Demo\IndexRead::nested([[1, 2]], 1, 0));
    attempt(fn () => Demo\IndexRead::orZero([10], 3));
    attempt(fn () => Demo\IndexRead::price(['pro' => 5], 'pro'));
    attempt(fn () => Demo\IndexRead::price(['pro' => 5], 'team'));
    attempt(fn () => Demo\IndexRead::literal(2));
    attempt(fn () => Demo\IndexRead::passed([1, 7], 0));
    attempt(fn () => Demo\IndexRead::passed([1, 7], 2));
    attempt(fn () => Demo\IndexRead::size(['a' => new Slots(4)], 'a'));
    attempt(fn () => Demo\IndexRead::size(['a' => new Slots(4)], 'b'));
    attempt(fn () => Demo\IndexRead::count(['a' => [1, 2]], 'a'));
    attempt(fn () => Demo\IndexRead::count(['a' => [1, 2]], 'b'));
    $output = ob_get_clean();
}

echo $output;

// A PHP read keeps PHP's warning, also inside an index, whose AST the compiler marks as the inner part of a chain.
$plain = [];
var_dump(@$plain[0][0], error_get_last()['message']);
?>
--EXPECT--
int(20)
OutOfRangeException: Undefined array key 2 in IndexRead.sharp on line 9
OutOfRangeException: Undefined array key -1 in IndexRead.sharp on line 9
int(2)
OutOfRangeException: Undefined array key 2 in IndexRead.sharp on line 14
OutOfRangeException: Undefined array key 1 in IndexRead.sharp on line 14
int(0)
int(5)
int(0)
OutOfRangeException: Undefined array key 2 in IndexRead.sharp on line 30
bool(false)
OutOfRangeException: Undefined array key 2 in IndexRead.sharp on line 36
int(4)
NULL
int(2)
NULL
NULL
string(37) "Trying to access array offset on null"
