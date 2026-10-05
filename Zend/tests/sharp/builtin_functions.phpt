--TEST--
PHP# calls PHP's built-in functions, and a namespace function of the same name never replaces one
--FILE--
<?php

namespace Demo {
    function count(\Countable $items): int
    {
        return -1;
    }
}

namespace {
    require __DIR__ . '/Expressions.sharp';

    echo Demo\Expressions::describe("cart", new ArrayObject([1, 2, 3])), "\n";
}
?>
--EXPECT--
cart has 3 items and a 3-byte token
