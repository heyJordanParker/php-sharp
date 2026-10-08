<?php

use Lib\Standing;

require __DIR__ . '/attempt.inc';
require __DIR__ . '/harness/Helper.inc';
require __DIR__ . '/harness/Standing.inc';
require __DIR__ . '/IndexRead.sharp';

for ($round = 0; $round < 100; $round++) {
    ob_start();
    attempt(fn () => Demo\IndexRead::at([10, 20], 1));
    attempt(fn () => Demo\IndexRead::at([10, 20], 2));
    attempt(fn () => Demo\IndexRead::at([10, 20], -1));
    attempt(fn () => Demo\IndexRead::nested([[1, 2]], 0, 1));
    attempt(fn () => Demo\IndexRead::nested([[1, 2]], 0, 2));
    attempt(fn () => Demo\IndexRead::nested([[1, 2]], 1, 0));
    attempt(fn () => Demo\IndexRead::orZero([10], 3));
    attempt(fn () => Demo\IndexRead::price(['pro' => 5], 'pro'));
    attempt(fn () => Demo\IndexRead::price(['pro' => 5], 'team'));
    attempt(fn () => Demo\IndexRead::literal(2));
    attempt(fn () => Demo\IndexRead::passed([1, 7], 0));
    attempt(fn () => Demo\IndexRead::passed([1, 7], 2));
    attempt(fn () => Demo\IndexRead::size(['a' => new SplFixedArray(4)], 'a'));
    attempt(fn () => Demo\IndexRead::size(['a' => new SplFixedArray(4)], 'b'));
    attempt(fn () => Demo\IndexRead::standing(['active' => 3], Standing::Active));
    attempt(fn () => Demo\IndexRead::standing(['active' => 3], Standing::Closed));
    $output = ob_get_clean();
}

echo $output;

// A PHP read keeps PHP's warning, also inside an index, whose AST the compiler marks as the inner part of a chain.
$plain = [];
var_dump(@$plain[0][0], error_get_last()['message']);
