--TEST--
A lazy `map` of a plain PHP closure is an `Iterable` of the result type the closure declares, spec section 12, read without running it, and of `Any?` when it declares none
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('PlainClosures');

use App\PlainClosures\Labels;

$calls = 0;
$typed = Labels::of([1, 2, 3], static function (int $id) use (&$calls): string { $calls++; return "#$id"; });
echo 'string result: ', var_export(Labels::areStrings($typed), true), ', ran ', $calls, "\n";
echo 'int result: ', var_export(Labels::areStrings(Labels::of([1, 2, 3], static fn (int $id): int => $id)), true), "\n";
echo 'no result type: ', var_export(Labels::areStrings(Labels::of([1, 2, 3], static fn ($id) => "#$id")), true), "\n";
echo implode(' ', iterator_to_array($typed)), ', ran ', $calls, "\n";
?>
--EXPECT--
string result: true, ran 0
int result: false
no result type: false
#1 #2 #3, ran 3
