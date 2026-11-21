--TEST--
A type test that names nothing converts the tested local when it matches, so each read the test narrows reads the Map or the Set the type names, and a test that fails leaves the local as it was
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use TypeTests\Narrowed;

echo 'keys: ', Narrowed::keys(['a' => 1]), ', ', Narrowed::keys('x'), "\n";
// The Map test fails on a list of strings and leaves it as it was, so the List test reads the plain array.
echo 'either: ', Narrowed::either(['a' => 1, 'b' => 2]), ', ', Narrowed::either(['a', 'b', 'c']), ', ', Narrowed::either('x'), "\n";
echo 'matched: ', Narrowed::matched(['a' => 1]), ', ', Narrowed::matched('x'), "\n";
var_dump(Narrowed::holdsTwo([1, 2]), Narrowed::holdsTwo([1]));
// A change made through a narrowed read reaches the local.
var_dump(Narrowed::grown([1]));
?>
--EXPECT--
keys: a, none
either: map a,b, list of 3, neither
matched: a, none
bool(true)
bool(false)
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
