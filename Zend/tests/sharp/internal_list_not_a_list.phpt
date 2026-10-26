--TEST--
Each Sharp\Internal function that takes a List refuses an array that is not a list, which plain PHP can pass, with a ValueError that names the function, and leaves the array as it was
--FILE--
<?php
$calls = [
    'add' => fn (array &$list) => Sharp\Internal\add($list, 'y'),
    'insert' => fn (array &$list) => Sharp\Internal\insert($list, 0, 'y'),
    'set' => fn (array &$list) => Sharp\Internal\set($list, 5, 'y'),
    'remove' => fn (array &$list) => Sharp\Internal\remove($list, 'x'),
    'removeAt' => fn (array &$list) => Sharp\Internal\removeAt($list, 5),
    'removeLast' => fn (array &$list) => Sharp\Internal\removeLast($list),
    'clear' => fn (array &$list) => Sharp\Internal\clear($list),
    'get' => fn (array &$list) => Sharp\Internal\get($list, 5),
    'contains' => fn (array &$list) => Sharp\Internal\contains($list, 'x'),
    'indexOf' => fn (array &$list) => Sharp\Internal\indexOf($list, 'x'),
    'nonEmpty' => fn (array &$list) => Sharp\Internal\nonEmpty($list),
    'entries' => fn (array &$list) => Sharp\Internal\entries($list),
    'toSet' => fn (array &$list) => Sharp\Internal\toSet($list),
    'filter' => fn (array &$list) => Sharp\Internal\filter($list, fn ($x) => true),
    'map' => fn (array &$list) => Sharp\Internal\map($list, fn ($x) => $x),
    'flatMap' => fn (array &$list) => Sharp\Internal\flatMap($list, fn ($x) => [$x]),
    'sumOf' => fn (array &$list) => Sharp\Internal\sumOf($list, fn ($x) => 1),
    'first' => fn (array &$list) => Sharp\Internal\first($list, fn ($x) => true),
    'any' => fn (array &$list) => Sharp\Internal\any($list, fn ($x) => true),
    'groupBy' => fn (array &$list) => Sharp\Internal\groupBy($list, fn ($x) => $x),
    'associateBy' => fn (array &$list) => Sharp\Internal\associateBy($list, fn ($x) => $x),
    'sortedBy' => fn (array &$list) => Sharp\Internal\sortedBy($list, fn ($x) => $x),
];
foreach ($calls as $name => $call) {
    $list = [5 => 'x'];
    try {
        $call($list);
        echo "$name: no error\n";
    } catch (ValueError $error) {
        echo $error->getMessage(), "\n";
    }
    if ($list !== [5 => 'x']) {
        echo "$name changed the array\n";
    }
}

// A list takes the fast path, and keeps working.
$list = ['a', 'b'];
Sharp\Internal\add($list, 'c');
echo Sharp\Internal\removeLast($list), Sharp\Internal\removeAt($list, 0), json_encode($list), "\n";
?>
--EXPECT--
Sharp\Internal\add(): Argument #1 ($list) must be a list
Sharp\Internal\insert(): Argument #1 ($list) must be a list
Sharp\Internal\set(): Argument #1 ($list) must be a list
Sharp\Internal\remove(): Argument #1 ($list) must be a list
Sharp\Internal\removeAt(): Argument #1 ($list) must be a list
Sharp\Internal\removeLast(): Argument #1 ($list) must be a list
Sharp\Internal\clear(): Argument #1 ($list) must be a list
Sharp\Internal\get(): Argument #1 ($list) must be a list
Sharp\Internal\contains(): Argument #1 ($list) must be a list
Sharp\Internal\indexOf(): Argument #1 ($list) must be a list
Sharp\Internal\nonEmpty(): Argument #1 ($list) must be a list
Sharp\Internal\entries(): Argument #1 ($list) must be a list
Sharp\Internal\toSet(): Argument #1 ($list) must be a list
Sharp\Internal\filter(): Argument #1 ($list) must be a list
Sharp\Internal\map(): Argument #1 ($list) must be a list
Sharp\Internal\flatMap(): Argument #1 ($list) must be a list
Sharp\Internal\sumOf(): Argument #1 ($list) must be a list
Sharp\Internal\first(): Argument #1 ($list) must be a list
Sharp\Internal\any(): Argument #1 ($list) must be a list
Sharp\Internal\groupBy(): Argument #1 ($list) must be a list
Sharp\Internal\associateBy(): Argument #1 ($list) must be a list
Sharp\Internal\sortedBy(): Argument #1 ($list) must be a list
ca["b"]
