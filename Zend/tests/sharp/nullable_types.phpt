--TEST--
PHP# nullable types take and return null, a parameter or a field whose default is null starts as null, and null is refused where the type is not nullable
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

// A parameter and a field whose default is null start as null.
var_dump($nulls->greeting(), $nulls->greeting('Ada'), $nulls->currentNickname());
$greeting = (new ReflectionMethod(Demo\Nulls::class, 'greeting'))->getParameters()[0];
echo $greeting->getType(), ' ', var_export($greeting->getDefaultValue(), true), "\n";
$nickname = new ReflectionProperty(Demo\Nulls::class, 'nickname');
echo $nickname->getType(), ' ', var_export($nickname->getDefaultValue(), true), "\n";

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
string(6) "nobody"
string(3) "Ada"
NULL
?string NULL
?string NULL
Demo\NullReturned::must(): Return value must be of type int, null returned on line 7
Demo\Nulls::same(): Argument #1 ($value) must be of type ?int, array given, called in %s on line %d
