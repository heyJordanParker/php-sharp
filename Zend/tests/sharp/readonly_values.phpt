--TEST--
PHP# readonly runs as the type it marks: plain PHP passes, changes and reflects the value as written without it
--FILE--
<?php
require __DIR__ . '/readonly_values.inc';
?>
--EXPECT--
bool(false)
0
3
A-1
5
Demo\ReadonlyValues\Ledger
Demo\ReadonlyValues\Ledger
sum 600
