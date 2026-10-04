--TEST--
PHP# raises CompileError at the .sharp line of a reassigned const
--FILE--
<?php

try {
    require __DIR__ . '/ConstReassigned.sharp';
} catch (CompileError $e) {
    echo get_class($e), ': ', $e->getMessage(), ' in ', basename($e->getFile()), ' on line ', $e->getLine(), "\n";
}
?>
--EXPECT--
CompileError: Cannot assign to `limit`: it is declared with `const`. in ConstReassigned.sharp on line 8
