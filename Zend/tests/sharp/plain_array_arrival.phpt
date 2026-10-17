--TEST--
PHP# renumbers plain PHP's int-keyed array where it arrives as a List, and a Map place keeps its keys
--FILE--
<?php
require __DIR__ . '/harness/Users.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('Arrivals');

$users = new Lib\Users();
$roster = new Arrivals\Roster();
echo $roster->first($users), "\n";
echo $roster->cachedFirst($users), "\n";
echo $roster->count($users), "\n";
echo implode(',', $roster->keys($users)), "\n";
echo implode(',', $roster->listed($users)), "\n";
// A plain PHP caller's int-keyed array enters a List parameter and a List property renumbered.
echo $roster->firstOf([5 => 7, 9 => 8]), "\n";
$roster->held = [4 => 3, 8 => 6];
echo $roster->heldFirst(), ' ', json_encode($roster->held), "\n";
try {
    $roster->firstOf(['a' => 1]);
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECTF--
10
10
2
5,9
1,2
7
3 [3,6]
Arrivals\Roster::firstOf(): Argument #1 ($items) must be of type List<int>, array given, called in %s on line %d
