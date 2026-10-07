--TEST--
PHP# raises CompileError at the .sharp line of a PHP cast alias
--FILE--
<?php

try {
    require __DIR__ . '/CastAlias.sharp';
} catch (CompileError $e) {
    echo get_class($e), ': ', $e->getMessage(), ' in ', basename($e->getFile()), ' on line ', $e->getLine(), "\n";
}
?>
--EXPECT--
CompileError: PHP# has no `(integer)`: write `(int)`. in CastAlias.sharp on line 7
