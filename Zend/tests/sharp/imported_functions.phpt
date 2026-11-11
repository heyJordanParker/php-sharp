--TEST--
An [Import] method runs the PHP function it names: each Map argument passes as a PHP array, the result crosses into the declared type, renumbered or made a Map, a result of another type throws a TypeError naming the function, and a function PHP doesn't declare throws at the call
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('Imports');

use Imports\Report;

var_dump(Report::distance());
var_dump(Report::unique());
var_dump(Report::position('y'), Report::position('w'));
var_dump(Report::keys());
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
TypeError: str_repeat(): Return value must be of type int, string returned
Error: Call to undefined function no_such_function()
