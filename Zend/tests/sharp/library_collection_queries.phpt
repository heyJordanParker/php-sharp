--TEST--
A PHP# class calls the collection members of Kotlin, Rust and .NET that PHP# lacked: firstOrNull on a List, a Map and a Set, indexOfFirst, minBy and maxBy, sortedByDescending, sortedWith, sortedBy by a tuple, zip, windowed, partition, binarySearch, partitionPoint, removeAll, getOrPut, and the union, intersection, difference and subset of two Sets, with each edge case
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Billing\Money;
use Demo\CollectionQueries;

var_dump(CollectionQueries::firstEven([1, 4, 6]), CollectionQueries::firstEven([1, 3]), CollectionQueries::firstEven([]));
var_dump(CollectionQueries::firstLong(['a' => 'tea', 'b' => 'honey', 'c' => 'jam']), CollectionQueries::firstLong(['a' => 'tea']));
var_dump(CollectionQueries::firstTag(['new', 'vip', 'vet']), CollectionQueries::firstTag([]));
var_dump(CollectionQueries::firstNegative([3, -1, -2]), CollectionQueries::firstNegative([3]));

echo CollectionQueries::shortest(['honey', 'tea', 'jam', 'pie']), ' ', CollectionQueries::longest(['tea', 'honey', 'syrup']), "\n";
foreach ([fn () => CollectionQueries::shortest([]), fn () => CollectionQueries::longest([])] as $refused) {
    try {
        $refused();
    } catch (OutOfRangeException $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}

echo json_encode(CollectionQueries::longestFirst(['tea', 'honey', 'jam', 'syrup', 'pie'])), "\n";
echo json_encode(CollectionQueries::byLastLetter(['tea', 'jam', 'kiwi', 'pea', 'ham'])), "\n";
echo json_encode(CollectionQueries::byLengthThenText(['pie', '10', 'tea', '9', 'apple', 'Zoo'])), "\n";
// Money's operator <=> orders by currency first, so the 900 EUR is the smallest price.
$prices = [new Money(500, 'USD'), new Money(200, 'USD'), new Money(500, 'USD'), new Money(900, 'EUR')];
echo json_encode(CollectionQueries::byPriceThenCount([0, 1, 2, 3], $prices)), "\n";
echo json_encode(CollectionQueries::byOptionalNameThenCount([4 => 'b', 2 => null, 3 => 'a', 1 => 'b', 5 => null])), "\n";

echo json_encode(CollectionQueries::zipped([1, 2, 3], ['a', 'b'])), ' ', json_encode(CollectionQueries::zipped([], ['a'])), "\n";
echo json_encode(CollectionQueries::windows([1, 2, 3, 4], 2)), ' ', json_encode(CollectionQueries::windows([1, 2], 3)), ' ', json_encode(CollectionQueries::windows([1, 2], 2)), "\n";
try {
    CollectionQueries::windows([1], 0);
} catch (ValueError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
echo json_encode(CollectionQueries::evensFirst([1, 2, 3, 4, 6])), ' ', json_encode(CollectionQueries::evensFirst([])), "\n";

var_dump(CollectionQueries::find([1, 3, 5, 7, 9], 7), CollectionQueries::find([1, 3, 5], 4), CollectionQueries::find([], 1));
var_dump(CollectionQueries::insertionPoint([1, 3, 5, 7], 4), CollectionQueries::insertionPoint([1, 3], 0), CollectionQueries::insertionPoint([1, 3], 9), CollectionQueries::insertionPoint([], 1));

echo json_encode(CollectionQueries::withoutNegatives([3, -1, 4, -5])), ' ', json_encode(CollectionQueries::withoutNegatives([1, 2])), "\n";

[$held, $added, $counts, $calls] = CollectionQueries::counted(['tea' => 3]);
echo $held, ' ', $added, ' ', json_encode($counts), ' ', $calls, "\n";

echo json_encode(CollectionQueries::union(['a', 'b'], ['b', 'c'])), ' ';
echo json_encode(CollectionQueries::intersect(['a', 'b', 'c'], ['c', 'a'])), ' ';
echo json_encode(CollectionQueries::subtract(['a', 'b', 'c'], ['b'])), "\n";
var_dump(CollectionQueries::isSubset(['a'], ['a', 'b']), CollectionQueries::isSubset(['a', 'z'], ['a', 'b']), CollectionQueries::isSubset([], []));
?>
--EXPECT--
int(4)
NULL
NULL
string(5) "honey"
NULL
string(3) "vip"
NULL
int(1)
NULL
tea honey
OutOfRangeException: The list is empty.
OutOfRangeException: The list is empty.
["honey","syrup","tea","jam","pie"]
["tea","pea","kiwi","jam","ham"]
["9","10","Zoo","pie","tea","apple"]
[3,1,2,0]
[2,5,3,1,4]
[[1,"a"],[2,"b"]] []
[[1,2],[2,3],[3,4]] [] [[1,2]]
ValueError: `windowed` takes a size of 1 or more, and it is smaller.
[[2,4,6],[1,3]] [[],[]]
int(3)
NULL
NULL
int(2)
int(0)
int(2)
int(0)
[true,[3,4]] [false,[1,2]]
3 4 {"tea":3,"jam":4} 1
["a","b","c"] ["a","c"] ["a","c"]
bool(true)
bool(false)
bool(true)
