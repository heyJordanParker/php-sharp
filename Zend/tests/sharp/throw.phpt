--TEST--
PHP# throws an exception from a statement and from an expression
--FILE--
<?php

require __DIR__ . '/Expressions.sharp';

foreach ([fn() => Demo\Expressions::positive(-1), fn() => Demo\Expressions::known(null)] as $call) {
    try {
        $call();
    } catch (InvalidArgumentException $e) {
        echo get_class($e), ': ', $e->getMessage(), ' in ', basename($e->getFile()), ' on line ', $e->getLine(), "\n";
    }
}
var_dump(Demo\Expressions::positive(2), Demo\Expressions::known(3));
?>
--EXPECT--
InvalidArgumentException: negative in Expressions.sharp on line 14
InvalidArgumentException: unknown in Expressions.sharp on line 21
int(2)
int(3)
