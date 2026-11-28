--TEST--
Class<T>.methods() reads a plain PHP method's `static` as the class value's class, a trait's `self` and `parent` as the class that uses the trait and its parent, a trait's `self|Linked` in `Linked` as the one class, and `never` as PHP#'s `never`
--FILE--
<?php
require __DIR__ . '/harness/Linked.inc';
require __DIR__ . '/vendor/autoload.php';

foreach (App\Linking\Report::describeMethods() as $line) {
    echo $line, "\n";
}
?>
--EXPECT--
parse(): Sharp.Result
copy(): App.Linking.Chain
fail(): never
maker(): Closure
selfMaker(): Closure
failer(): Closure
itself(): Lib.Linked
root(): Lib.Root
either(): Lib.Linked
