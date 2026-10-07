--TEST--
PHP# (int) of a float literal becomes an int at compile time only when it cannot throw ArithmeticError
--FILE--
<?php

require __DIR__ . '/LiteralCast.sharp';

foreach (['half', 'huge', 'below'] as $method) {
    try {
        var_dump(Demo\LiteralCast::$method());
    } catch (ArithmeticError $e) {
        echo get_class($e), ': ', $e->getMessage(), ' in ', basename($e->getFile()), ' on line ', $e->getLine(), "\n";
    }
}
?>
--EXPECT--
int(2)
ArithmeticError: The float 1.0E+300 is not representable as an int in LiteralCast.sharp on line 12
ArithmeticError: The float -1.0E+19 is not representable as an int in LiteralCast.sharp on line 17
