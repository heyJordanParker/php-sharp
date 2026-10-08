--TEST--
PHP# raises CompileError at the .sharp line of a (bool) cast
--FILE--
<?php

try {
    require __DIR__ . '/BoolCast.sharp';
} catch (CompileError $e) {
    echo get_class($e), ': ', $e->getMessage(), ' in ', basename($e->getFile()), ' on line ', $e->getLine(), "\n";
}
?>
--EXPECT--
CompileError: PHP# has no `(bool)`: compare the value instead, as in `count > 0` or `flag == "1"`. in BoolCast.sharp on line 7
