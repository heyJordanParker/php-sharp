--TEST--
A PHP# class that declares operator == with hash() keys a Map and joins a Set: two distinct equal objects are one entry
--FILE--
<?php
require __DIR__ . '/Pass.sharp';
require __DIR__ . '/ClassKeys.sharp';

use Demo\ClassKeys;

// Each round runs every row, so the JIT variants compile them before the last round, whose output is printed.
for ($round = 0; $round < 100; $round++) {
    ob_start();
    $scans = ClassKeys::scans();
    echo 'count, value of an equal key, Set count, Set contains: ', implode(' ', ClassKeys::read($scans, ClassKeys::seen())), "\n";
    echo 'the stored keys: ', implode(', ', ClassKeys::holders($scans)), "\n";
    echo 'plain PHP counts: ', count($scans), "\n";
    $output = ob_get_clean();
}
echo $output;
?>
--EXPECT--
count, value of an equal key, Set count, Set contains: 2 2 2 1
the stored keys: Ada, Ada
plain PHP counts: 2
