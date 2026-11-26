--TEST--
A plain PHP array keyed by int alone arrives as a List, as spec section 12 says, so a non-empty one is a Map only where the key type takes int: [1] fails `is Map<string, Any?>` and passes `is Map<int, Any?>`, no test converts it into an object, and an empty array still passes every collection test
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\ListsAsMaps;

echo implode("\n", ListsAsMaps::children()), "\n";
echo implode("\n", ListsAsMaps::tests()), "\n";
?>
--EXPECT--
a: [1]
b: []
[1] is Map<string, Any?>: false
[1] is Map<int, Any?>: true
