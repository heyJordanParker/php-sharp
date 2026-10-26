--TEST--
The engine functions the standard library's List members run change the List they are given by reference where it lives, copy a shared one first, set gives the element it replaced, and they throw OutOfRangeException with the messages a List's index and last throw
--FILE--
<?php
use function Sharp\Internal\{add, insert, set, remove, removeAt, removeLast, clear, get, contains, indexOf, nonEmpty};

$list = [1, 2, 3];
$shared = $list;
add($list, 4);
insert($list, 0, 0);
var_dump(set($list, 1, 9));
var_dump(remove($list, 3), remove($list, 7));
echo json_encode($list), ' ', json_encode($shared), "\n";

var_dump(removeAt($list, 1));
echo json_encode($list), "\n";
var_dump(removeLast($list));
echo json_encode($list), "\n";
add($list, 5);
echo json_encode($list), "\n";

var_dump(get($list, 1), get($list, 7), contains($list, 2.0), contains($list, 6), indexOf($list, 5), indexOf($list, 6));
var_dump(nonEmpty($list) === $list);

$copy = $list;
clear($list);
echo json_encode($list), ' ', json_encode($copy), "\n";

foreach ([
    fn () => removeAt($list, 0),
    fn () => removeLast($list),
    fn () => nonEmpty($list),
    fn () => removeAt($copy, 5),
    fn () => set($copy, 3, 1),
    fn () => insert($copy, 4, 1),
] as $refused) {
    try {
        $refused();
    } catch (OutOfRangeException $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
int(1)
bool(true)
bool(false)
[0,9,2,4] [1,2,3]
int(9)
[0,2,4]
int(4)
[0,2]
[0,2,5]
int(2)
NULL
bool(true)
bool(false)
int(2)
NULL
bool(true)
[] [0,2,5]
OutOfRangeException: The list is empty.
OutOfRangeException: The list is empty.
OutOfRangeException: The list is empty.
OutOfRangeException: Undefined array key 5
OutOfRangeException: Undefined array key 3
OutOfRangeException: Undefined array key 4
