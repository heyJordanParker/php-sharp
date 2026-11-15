--TEST--
PHP# x is int? matches an int or null, not binds tighter than or, and a match statement takes expression arms
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('Matched');

var_dump(Matched\Checks::maybe(1), Matched\Checks::maybe(null), Matched\Checks::maybe('1'));
var_dump(Matched\Checks::other('a'), Matched\Checks::other(5), Matched\Checks::other(6));
echo Matched\Checks::noted(0), ' ', Matched\Checks::noted(1), "\n";
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
bool(true)
bool(true)
bool(false)
zero other
