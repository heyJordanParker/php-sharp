--TEST--
PHP# raises CompileError at the .sharp line of a static computed property
--FILE--
<?php

try {
    require __DIR__ . '/StaticComputed.sharp';
} catch (CompileError $e) {
    echo get_class($e), ': ', $e->getMessage(), ' in ', basename($e->getFile()), ' on line ', $e->getLine(), "\n";
}
?>
--EXPECT--
CompileError: A static computed property is not supported yet in PHP#. in StaticComputed.sharp on line 5
