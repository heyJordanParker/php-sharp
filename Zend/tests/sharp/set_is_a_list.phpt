--TEST--
A PHP array is a Set only when it is a list or keyed by int alone, as spec section 12's "a Set the list of its elements" says: a JSON object fails `is Set<string|int>`, so no test converts it, and it stays a Map with its keys, while a JSON array of strings is a Set<string>, and so is plain PHP's array keyed by int with a gap
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\ParsedSets;

echo implode("\n", ParsedSets::form()), "\n";
echo implode("\n", ParsedSets::tags()), "\n";
echo ParsedSets::gapped([5, 2 => 2, 5]), "\n";
?>
--EXPECT--
is Set<string|int>: false
is Map<string, Any?>: true
after both tests: {"email":"ada@example.com","consent":"yes"}
is Set<string>: true
after the test: ["new","vip"]
[5, 2 => 2, 5] is Set<int>: true
