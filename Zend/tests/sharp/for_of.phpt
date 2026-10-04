--TEST--
PHP# runs for … of loops over the values, or the keys and values, of a PHP array
--FILE--
<?php

namespace Demo;

class Prices
{
    public static function all(): array
    {
        return [10 => 3, 20 => 4];
    }
}

require __DIR__ . '/ControlFlow.sharp';

echo ControlFlow::weigh(2), "\n";
?>
--EXPECT--
44
