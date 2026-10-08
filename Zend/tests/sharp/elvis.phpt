--TEST--
PHP# raises CompileError at the .sharp line of PHP's two-operand ternary
--FILE--
<?php

try {
    require __DIR__ . '/Elvis.sharp';
} catch (CompileError $e) {
    echo get_class($e), ': ', $e->getMessage(), ' in ', basename($e->getFile()), ' on line ', $e->getLine(), "\n";
}
?>
--EXPECT--
CompileError: PHP# has no `?:`: write `a ?? b` to replace null, or `c ? a : b` with a `bool` condition. in Elvis.sharp on line 7
