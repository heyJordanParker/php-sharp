--TEST--
A key write, an append and a removal on an extern collection property change a copy, which the plain PHP parent's __set stores, spec section 14.3, so its JSON-decoding __get reads each change back
--FILE--
<?php
require __DIR__ . '/harness/JsonRow.inc';
require __DIR__ . '/vendor/autoload.php';

echo implode("\n", ExternProperties\Answers::edit('color', 'size')), "\n";
?>
--EXPECT--
size: blue
lines: 2, last second
color: none
