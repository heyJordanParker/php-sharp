--TEST--
A type test that names nothing converts the tested local when it matches where it narrows a later read, so each read the test narrows reads the Map or the Set the type names, a test that fails leaves the local as it was, and a test used as a value leaves it as it was
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use TypeTests\Narrowed;

echo 'keys: ', Narrowed::keys(['a' => 1]), ', ', Narrowed::keys('x'), "\n";
// The Map test fails on a list of strings and leaves it as it was, so the List test reads the plain array.
echo 'either: ', Narrowed::either(['a' => 1, 'b' => 2]), ', ', Narrowed::either(['a', 'b', 'c']), ', ', Narrowed::either('x'), "\n";
echo 'matched: ', Narrowed::matched(['a' => 1]), ', ', Narrowed::matched('x'), "\n";
var_dump(Narrowed::holdsTwo([1, 2]), Narrowed::holdsTwo([1]));
var_dump(Narrowed::onlyA(['a' => 1]), Narrowed::onlyA(['b' => 1]));
// Each test in the list is a value, which narrows nothing, so the Map test leaves the empty array a plain array the List test passes.
echo 'both: ', json_encode(Narrowed::both([])), "\n";
// A test under `!` or `||` in a condition narrows the reads after a failed test, as a guard clause's return leaves them.
echo 'counted: ', Narrowed::counted([1, 2]), ', ', Narrowed::counted([]), "\n";
echo 'keyed: ', Narrowed::keyed(['a' => 1]), ', ', Narrowed::keyed([]), ', ', Narrowed::keyed('x'), "\n";
echo 'guarded: ', Narrowed::guarded(['a' => 1]), ', ', Narrowed::guarded('x'), "\n";
// A change made through a narrowed read reaches the local.
var_dump(Narrowed::grown([1]));
?>
--EXPECT--
keys: a, none
either: map a,b, list of 3, neither
matched: a, none
bool(true)
bool(false)
bool(true)
bool(false)
both: [true,true]
counted: 2, 0
keyed: a, empty, empty
guarded: a, none
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
