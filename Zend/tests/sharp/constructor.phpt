--TEST--
PHP# runs the constructor named after its class as __construct
--FILE--
<?php

require __DIR__ . '/Account.sharp';

$constructor = (new ReflectionClass(Demo\Account::class))->getConstructor();
echo $constructor->getName(), ' ', $constructor->getStartLine(), '-', $constructor->getEndLine(),
    $constructor->hasReturnType() ? ' typed' : ' untyped', "\n";

echo (new Demo\Account(5))->deposit(2), "\n";
echo Demo\Account::open(2), "\n";

try {
    new Demo\Account('five');
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECTF--
__construct 7-10 untyped
7
3
Demo\Account::__construct(): Argument #1 ($opening) must be of type int, string given, called in %s on line %d
