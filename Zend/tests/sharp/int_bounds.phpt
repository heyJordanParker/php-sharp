--TEST--
PHP# reads the int bounds as Int.MAX and Int.MIN, and Int.MIN / -1 overflows
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Int.sharp';
require __DIR__ . '/harness/fixture.inc';
fixture('IntBounds');

var_dump(IntBounds\Bounds::largest() === PHP_INT_MAX, IntBounds\Bounds::smallest() === PHP_INT_MIN);
echo IntBounds\Bounds::overflow(), "\n";
?>
--EXPECT--
bool(true)
bool(true)
overflows
