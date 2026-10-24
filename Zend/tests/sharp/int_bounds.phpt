--TEST--
PHP# reads the int bounds as Int.MAX and Int.MIN, and Int.MIN / -1 and Int.MIN.floorDiv(-1) overflow with a message that names Int.MIN, where plain PHP's intdiv names PHP_INT_MIN
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Int.sharp';
require __DIR__ . '/harness/fixture.inc';
fixture('IntBounds');
require __DIR__ . '/int_bounds.inc';
echo int_bounds_rounds();
?>
--EXPECT--
true true
Division of Int.MIN by -1 overflows an int
Division of Int.MIN by -1 overflows an int
Division of PHP_INT_MIN by -1 is not an integer
