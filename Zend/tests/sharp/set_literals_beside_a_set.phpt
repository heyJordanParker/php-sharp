--TEST--
A list literal beside a Set in ??, ? : or a match is that Set, so adding an element twice keeps it once
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

echo json_encode(SetValues\Owners::grouped(['a', 'b', 'a'])), "\n";
echo json_encode(SetValues\Owners::branches([], false, 0)), "\n";
?>
--EXPECT--
[2,1]
[1,1]
