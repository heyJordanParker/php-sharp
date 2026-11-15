--TEST--
An [Import] method runs the PHP function it names: each Map argument passes as a PHP array, at every depth and whatever type holds it, the result crosses into the declared type, renumbered or made a Map, a result of another type throws a TypeError naming the function, and a function PHP doesn't declare throws at the call. A plain PHP function takes each Map as a PHP array too, held as Any?, in a List, in a union or as a generic member's type parameter, and a result whose conditional type resolves to array_slice's int-keyed branch arrives as a List renumbered from 0
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
require __DIR__ . '/harness/Arrays.inc';
fixture('Imports');

use Imports\Report;

var_dump(Report::distance());
var_dump(Report::unique());
var_dump(Report::position('y'), Report::position('w'));
var_dump(Report::keys());
var_dump(Report::heldMaps());
var_dump(Report::passedMaps());
var_dump(Report::sliced());
foreach (['repeated', 'missing'] as $method) {
    try {
        Report::$method();
    } catch (Throwable $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
int(3)
array(3) {
  [0]=>
  string(1) "a"
  [1]=>
  string(1) "b"
  [2]=>
  string(1) "c"
}
int(1)
int(-1)
array(2) {
  [0]=>
  string(3) "tea"
  [1]=>
  string(4) "milk"
}
array(3) {
  [0]=>
  string(33) "a:1:{i:0;a:1:{s:2:"id";s:1:"a";}}"
  [1]=>
  string(70) "Array
(
    [0] => Array
        (
            [id] => a
        )

)
"
  [2]=>
  string(1) "a"
}
array(4) {
  [0]=>
  string(33) "a:1:{i:0;a:1:{s:2:"id";s:1:"a";}}"
  [1]=>
  string(33) "a:1:{i:0;a:1:{s:2:"id";s:1:"a";}}"
  [2]=>
  string(33) "a:1:{i:0;a:1:{s:2:"id";s:1:"a";}}"
  [3]=>
  string(23) "a:1:{s:2:"id";s:1:"a";}"
}
array(4) {
  [0]=>
  int(6)
  [1]=>
  int(2)
  [2]=>
  int(6)
  [3]=>
  int(2)
}
TypeError: str_repeat(): Return value must be of type int, string returned
Error: Call to undefined function no_such_function()
