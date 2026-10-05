--TEST--
PHP# raises CompileError at the .sharp line of a Map key that is not int or string
--FILE--
<?php

try {
    require __DIR__ . '/MapKey.sharp';
} catch (CompileError $e) {
    echo get_class($e), ': ', $e->getMessage(), ' in ', basename($e->getFile()), ' on line ', $e->getLine(), "\n";
}
?>
--EXPECT--
CompileError: A `Map`'s keys are `int` or `string`, as a PHP array's keys are. in MapKey.sharp on line 5
