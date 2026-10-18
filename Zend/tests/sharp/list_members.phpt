--TEST--
A List's flatMap gives the elements of each Iterable its function gives, a List, a Set, a generator or a Map's [key, value] entries, in order, an empty List gives an empty List, and a function that gives no Iterable throws TypeError; toList gives a Set's elements, a Map's entries, a Map's values and a List's own elements as a List of their own, a Map or a Set read through an index of a List too
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('ListMembers');

use ListMembers\Shelf;

echo json_encode(Shelf::flat([[1, 2], [], [3]])), "\n";
echo json_encode(Shelf::flat([])), "\n";
echo json_encode(Shelf::tags([['a', 'b'], ['c']])), "\n";
echo json_encode(Shelf::doubled([[1, 2], [3]])), "\n";
try {
    Shelf::flatWith([1], fn ($n) => $n);
} catch (TypeError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
echo json_encode(Shelf::entries([new Sharp\Map(['a', 1, 'b', 2]), new Sharp\Map([]), new Sharp\Map(['c', 3])])), "\n";

echo json_encode(Shelf::fromSet(['b', 'a', 'c'])), "\n";
echo json_encode(Shelf::fromValues(['tea' => 3, 'pie' => 7])), "\n";
echo json_encode(Shelf::fromMap(new Sharp\Map(['tea', 3, 'pie', 7]))), "\n";
echo json_encode(Shelf::fromMap(new Sharp\Map([]))), "\n";
echo json_encode(Shelf::fromFirstMap([new Sharp\Map(['tea', 3])])), "\n";
echo json_encode(Shelf::fromFirstSet([new Sharp\Set(['b', 'a'])])), "\n";
echo json_encode(Shelf::copied([4, 5])), "\n";
?>
--EXPECT--
[1,2,3]
[]
["a","b","c"]
[1,1,2,2,3,3]
TypeError: flatMap() takes a function that gives an Iterable, and it gave int
[["a",1],["b",2],["c",3]]
["b","a","c"]
[3,7]
[["tea",3],["pie",7]]
[]
[["tea",3]]
["b","a"]
[2,3]
