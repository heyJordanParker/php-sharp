--TEST--
PHP# nullable types take and return null, and refuse it where the type is not nullable
--FILE--
<?php

require __DIR__ . '/harness/Helper.inc';
require __DIR__ . '/Nulls.sharp';
require __DIR__ . '/NullReturned.sharp';

$nulls = new Demo\Nulls;
$returned = new Demo\NullReturned;
var_dump(Demo\Nulls::none(), $nulls->same(null), $nulls->same(3), $returned->must(4));
echo (new ReflectionMethod(Demo\Nulls::class, 'same'))->getParameters()[0]->getType(), "\n";
echo (new ReflectionMethod(Demo\Nulls::class, 'none'))->getReturnType(), "\n";

try {
    $returned->must(null);
} catch (TypeError $e) {
    echo $e->getMessage(), ' on line ', $e->getLine(), "\n";
}

try {
    $nulls->same([]);
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECTF--
NULL
NULL
int(3)
int(4)
?int
?Demo\Nulls
Demo\NullReturned::must(): Return value must be of type int, null returned on line 7
Demo\Nulls::same(): Argument #1 ($value) must be of type ?int, array given, called in %s on line %d
