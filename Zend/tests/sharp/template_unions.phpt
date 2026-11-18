--TEST--
PHP# a template shows a union of int, float, string and bool, each value as its own type shows
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

echo Shown\Labels::of(5), "\n";
echo Shown\Labels::of('five'), "\n";
echo Shown\Labels::flag(true), "\n";
echo Shown\Labels::flag(false), "\n";
echo Shown\Labels::flag(3), "\n";
echo Shown\Labels::counted(1.5), "\n";
echo Shown\Labels::counted(2), "\n";
?>
--EXPECT--
value 5
value five
flag true
flag false
flag 3
counted 1.5
counted 2
