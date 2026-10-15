--TEST--
A Map keyed by string|Standing holds "active" and Standing::Active as two entries, each read back as its own key type
--FILE--
<?php
require __DIR__ . '/harness/Standing.inc';
require __DIR__ . '/MixedKeys.sharp';

use Demo\MixedKeys;

// Each round runs every row, so the JIT variants compile them before the last round, whose output is printed.
for ($round = 0; $round < 100; $round++) {
    ob_start();
    $tally = MixedKeys::tally();
    echo 'get, index, keys and count: ', implode(' ', MixedKeys::read($tally)), "\n";
    echo 'the loop: ', implode(', ', MixedKeys::described($tally)), "\n";
    foreach ($tally as $key => $n) {
        echo 'plain PHP: ', get_debug_type($key), ' => ', $n, "\n";
    }
    $output = ob_get_clean();
}
echo $output;
?>
--EXPECT--
get, index, keys and count: 11 2 2 2
the loop: the string active, the case Active
plain PHP: string => 11
plain PHP: Lib\Standing => 2
