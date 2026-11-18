--TEST--
PHP# compiles an internal struct, which code of its own namespace creates, reads and compares as any struct, spec sections 5 and 10
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\InternalStructs\Checkout;

var_dump(Checkout::total(2500), Checkout::same(2500));
?>
--EXPECT--
int(2999)
bool(true)
