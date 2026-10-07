--TEST--
PHP# raises CompileError at the .sharp line of a `match` without a `default` arm
--FILE--
<?php

try {
    require __DIR__ . '/MatchWithoutDefault.sharp';
} catch (CompileError $e) {
    echo get_class($e), ': ', $e->getMessage(), ' in ', basename($e->getFile()), ' on line ', $e->getLine(), "\n";
}
?>
--EXPECT--
CompileError: A `match` needs a `default` arm. in MatchWithoutDefault.sharp on line 7
