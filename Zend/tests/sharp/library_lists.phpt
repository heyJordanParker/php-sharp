--TEST--
A PHP# class calls every member of List in Sharp: reversed, distinct, slice, chunked, all and fold, slice throws OutOfRangeException for a range outside the list, and chunked throws ValueError for a size below 1
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/ListMembers.sharp';
require __DIR__ . '/ListCalls.sharp';

use Demo\ListCalls;

echo json_encode(ListCalls::reversed([1, 2, 3])), ' ', json_encode(ListCalls::reversed([])), "\n";
echo json_encode(ListCalls::distinct(['a', 'b', 'a', 'c', 'b'])), "\n";
echo json_encode(ListCalls::slice([1, 2, 3, 4], 1)), ' ', json_encode(ListCalls::between([1, 2, 3, 4], 1, 3)), ' ', json_encode(ListCalls::between([1, 2], 2, 2)), "\n";
echo json_encode(ListCalls::chunked([1, 2, 3, 4, 5], 2)), ' ', json_encode(ListCalls::chunked([], 3)), "\n";
var_dump(ListCalls::allPositive([1, 2]), ListCalls::allPositive([1, -2]), ListCalls::allPositive([]));
var_dump(ListCalls::total([1, 2, 3]), ListCalls::total([]), ListCalls::joined(['a', 'b']));

foreach ([fn () => ListCalls::slice([1], 2), fn () => ListCalls::between([1, 2, 3], 2, 1), fn () => ListCalls::slice([1], -1)] as $refused) {
    try {
        $refused();
    } catch (OutOfRangeException $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}
try {
    ListCalls::chunked([1], 0);
} catch (ValueError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
[3,2,1] []
["a","b","c"]
[2,3,4] [2,3] []
[[1,2],[3,4],[5]] []
bool(true)
bool(false)
bool(true)
int(6)
int(0)
string(2) "ab"
OutOfRangeException: `slice` takes a range inside the list, and the range is outside it.
OutOfRangeException: `slice` takes a range inside the list, and the range is outside it.
OutOfRangeException: `slice` takes a range inside the list, and the range is outside it.
ValueError: `chunked` takes a size of 1 or more, and it is smaller.
