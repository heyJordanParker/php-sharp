--TEST--
A PHP# class header with two classes, or with a name that is neither a class nor an interface, fails to link
--FILE--
<?php

require __DIR__ . '/harness/Header.inc';

foreach (['HeaderTwoClasses', 'HeaderNeither', 'HeaderMissing'] as $file) {
    try {
        require __DIR__ . "/$file.sharp";
    } catch (Error $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
Class Demo\Twice cannot extend both Lib\Record and Lib\Other
Class Demo\Blend cannot inherit from Lib\Mixin, which is neither a class nor an interface
Class Demo\Lost cannot inherit from Lib\Missing, which is neither a class nor an interface
