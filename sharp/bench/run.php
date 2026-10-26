<?php

// Runs one row of sharp/bin/bench: `php sharp/bench/run.php <row> <n>`.
require __DIR__ . '/../composer/library/Sharp/ListMembers.sharp';
require __DIR__ . '/Loops.sharp';

[, $row, $n] = $argv;
echo Bench\Loops::$row((int) $n), "\n";
