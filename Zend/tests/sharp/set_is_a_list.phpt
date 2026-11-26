--TEST--
A PHP array is a Set only when it is a list, as spec section 12's "a Set the list of its elements" says: a JSON object fails `is Set<string|int>`, so no test converts it, and it stays a Map with its keys, while a JSON array of strings is a Set<string>
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\ParsedSets;

echo implode("\n", ParsedSets::form()), "\n";
echo implode("\n", ParsedSets::tags()), "\n";
?>
--EXPECT--
is Set<string|int>: false
is Map<string, Any?>: true
after both tests: {"email":"ada@example.com","consent":"yes"}
is Set<string>: true
after the test: ["new","vip"]
