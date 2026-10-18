--TEST--
A Map keyed by string|Standing holds "active" and Standing::Active as two entries, each read back as its own key type, and plain PHP gets a ValueError for it, since its PHP array would hold one
--FILE--
<?php
require __DIR__ . '/harness/Standing.inc';
require __DIR__ . '/MixedKeys.sharp';

use Demo\MixedKeys;

// Each round runs every row, so the JIT variants compile them before the last round, whose output is printed.
for ($round = 0; $round < 100; $round++) {
    ob_start();
    echo 'get, index, keys and count: ', implode(' ', MixedKeys::read()), "\n";
    echo 'the loop: ', implode(', ', MixedKeys::described()), "\n";
    try {
        MixedKeys::tally();
    } catch (ValueError $e) {
        echo 'plain PHP: ', $e->getMessage(), "\n";
    }
    $output = ob_get_clean();
}
echo $output;
?>
--EXPECT--
get, index, keys and count: 11 2 2 2
the loop: the string active, the case Active
plain PHP: This `Map` holds both `"active"` and `Lib.Standing.Active`, which plain PHP keeps as one key, so it can't become a plain PHP array
