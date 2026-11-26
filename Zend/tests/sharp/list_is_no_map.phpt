--TEST--
A plain PHP array keyed by int alone arrives as a List, as spec section 12 says, so a non-empty list is a Map only where the key type takes int, while ['42' => true], whose key PHP stores as an int but which is no list, is a Map<string, bool>: [1] fails `is Map<string, Any?>` and passes `is Map<int, Any?>`, no test converts it into an object, and an empty array still passes every collection test, the Map test first here
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\ListsAsMaps;

echo implode("\n", ListsAsMaps::children()), "\n";
echo implode("\n", ListsAsMaps::tests()), "\n";
// PHP stores the key '42' as the int 42, so this array has int keys alone, but it isn't a list: a Map of string keys.
echo ListsAsMaps::numericKey(['42' => true]), "\n";
?>
--EXPECT--
a: [1]
b: {}
[1] is Map<string, Any?>: false
[1] is Map<int, Any?>: true
['42' => true] is Map<string, bool>: true
