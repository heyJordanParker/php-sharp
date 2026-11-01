--TEST--
Each Sharp\Internal function a Map's or a Set's member runs reads, counts or changes its table, a change separates a table another variable shares only when it changes, and a value of another kind is a TypeError that names what the function takes
--FILE--
<?php
$map = new Sharp\Map(['a', 1, 'b', 2, 'c', 3]);
$set = new Sharp\Set(['x', 'y']);
var_dump(Sharp\Internal\size($map), Sharp\Internal\size($set));
var_dump(Sharp\Internal\containsKey($map, 'a'), Sharp\Internal\containsKey($set, 'z'));
echo json_encode(Sharp\Internal\keys($map)), ' ', json_encode(Sharp\Internal\keys($set)), ' ',
    json_encode(Sharp\Internal\values($map)), ' ', json_encode(Sharp\Internal\pairs($map)), "\n";
var_dump(Sharp\Internal\get($map, 'b'), Sharp\Internal\get($map, 'z'));
echo json_encode(Sharp\Internal\map($set, fn ($element) => strtoupper($element))), ' ',
    Sharp\Internal\sumOf($map, fn ($value) => $value), ' ',
    Sharp\Internal\first($set, fn ($element) => $element === 'y'), ' ',
    var_export(Sharp\Internal\any($map, fn ($value) => $value > 2), true), ' ',
    json_encode(Sharp\Internal\sortedBy($map, fn ($value) => -$value)), "\n";
echo json_encode(Sharp\Internal\filterEntries($map, fn ($value) => $value > 1)), ' ',
    json_encode(Sharp\Internal\filterElements($set, fn ($element) => $element === 'x')), ' ',
    json_encode(Sharp\Internal\groupBy($map, fn ($value) => $value % 2)), ' ',
    json_encode(Sharp\Internal\associateBy($set, fn ($element) => $element . '!')), "\n";

// A change separates a table another variable shares, and one that changes nothing leaves it shared.
$shared = $map;
var_dump(Sharp\Internal\removeKey($shared, 'z'), $shared === $map);
var_dump(Sharp\Internal\removeKey($shared, 'a'), $shared === $map, count($map), count($shared));
$tags = $set;
var_dump(Sharp\Internal\addKey($tags, 'x'), $tags === $set);
var_dump(Sharp\Internal\addKey($tags, 'z'), $tags === $set, count($set), count($tags));
$cleared = $set;
Sharp\Internal\clear($cleared);
echo count($set), ' ', count($cleared), ' ', get_class($cleared), "\n";

foreach ([
    fn () => Sharp\Internal\size(['a' => 1]),
    fn () => Sharp\Internal\containsKey([1], 1),
    fn () => Sharp\Internal\keys('x'),
    fn () => Sharp\Internal\map(5, fn ($value) => $value),
    fn () => Sharp\Internal\get($set, 'x'),
    fn () => Sharp\Internal\get([1], 'a'),
] as $wrong) {
    try {
        $wrong();
    } catch (TypeError $error) {
        echo $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
int(3)
int(2)
bool(true)
bool(false)
["a","b","c"] ["x","y"] [1,2,3] [["a",1],["b",2],["c",3]]
int(2)
NULL
["X","Y"] 6 y true [3,2,1]
{"b":2,"c":3} ["x"] {"1":[1,3],"0":[2]} {"x!":"x","y!":"y"}
bool(false)
bool(true)
bool(true)
bool(false)
int(3)
int(2)
bool(false)
bool(true)
bool(true)
bool(false)
int(2)
int(3)
2 0 Sharp\Set
Sharp\Internal\size(): Argument #1 ($table) must be of type Sharp\Map|Sharp\Set, array given
Sharp\Internal\containsKey(): Argument #1 ($table) must be of type Sharp\Map|Sharp\Set, array given
Sharp\Internal\keys(): Argument #1 ($table) must be of type Sharp\Map|Sharp\Set, string given
Sharp\Internal\map(): Argument #1 ($collection) must be of type Sharp\Map|Sharp\Set|array, int given
Sharp\Internal\get(): Argument #1 ($collection) must be of type Sharp\Map|array, Sharp\Set given
Sharp\Internal\get(): Argument #2 ($key) must be of type int for a List, string given
