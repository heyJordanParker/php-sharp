--TEST--
A List, Map or Set from plain PHP, an argument or a list a plain PHP docblock claims, is checked element by element where PHP# takes it, and an int in a float's place becomes a float in a copy
--FILE--
<?php
require __DIR__ . '/type_arguments_entry.inc';

use Checks\Tally;
use Lib\Pager;

attempt(fn () => Tally::sizes([1, 2], ['vip' => 1.5, 5 => 2.0], ['a' => 'a']));
attempt(fn () => Tally::sizes([1, 'a'], [], []));
attempt(fn () => Tally::sizes(['first' => 1], [], []));
attempt(fn () => Tally::sizes([], ['vip' => 'cheap'], []));
attempt(fn () => Tally::sizes([], [], ['a' => 7]));
attempt(fn () => Tally::rows([[1], [2, 'x']]));
$amounts = [1, 2.5];
var_dump(Tally::first($amounts), $amounts[0]);
Pager::$sizes = [3, 7];
attempt(fn () => Tally::largest());
Pager::$sizes = [3, 'seven'];
attempt(fn () => Tally::largest());
?>
--EXPECTF--
sized
Checks\Tally::sizes(): Argument #1 ($sizes) must be of type List<int>, array given: [1] expected int, got string 'a', called in %s on line %d
Checks\Tally::sizes(): Argument #1 ($sizes) must be of type List<int>, array given, called in %s on line %d
Checks\Tally::sizes(): Argument #2 ($prices) must be of type Map<string, float>, array given: ["vip"] expected float, got string 'cheap', called in %s on line %d
Checks\Tally::sizes(): Argument #3 ($tags) must be of type Set<string>, array given: ["a"] expected string, got int 7, called in %s on line %d
Checks\Tally::rows(): Argument #1 ($rows) must be of type List<List<int>>, array given: [1][1] expected int, got string 'x', called in %s on line %d
float(1)
int(1)
7
Lib\Pager::sizes(): Return value must be of type List<int>, array returned: [1] expected int, got string 'seven'
