--TEST--
PHP# reaches PHP's built-in levenshtein through a project's plain PHP class that wraps it, and a library member that runs a PHP function runs PHP's own, never a namespace function of the same name
--FILE--
<?php

namespace Demo {
    function mb_strlen(string $text, ?string $encoding = null): int
    {
        return -1;
    }
}

namespace {
    require __DIR__ . '/harness/Words.inc';
    require __DIR__ . '/Expressions.sharp';

    echo Demo\Expressions::describe("cart", new ArrayObject([1, 2, 3]), "carts"), "\n";
}
?>
--EXPECT--
cart has 3 items, 4 letters and 1 edit from carts
