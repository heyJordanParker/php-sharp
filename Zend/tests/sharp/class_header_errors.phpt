--TEST--
A PHP# class header with two classes or a missing name fails to link, and an interface or enum header refuses a trait
--FILE--
<?php

require __DIR__ . '/Header.inc';

foreach (['HeaderTwoClasses', 'HeaderMissing'] as $file) {
    try {
        require __DIR__ . "/$file.sharp";
    } catch (Error $e) {
        echo $e->getMessage(), "\n";
    }
}

// Each refusal is fatal, so each file runs in its own process.
foreach (['HeaderNeither', 'HeaderEnumTrait'] as $file) {
    $code = 'require ' . var_export(__DIR__ . '/Header.inc', true) . '; require ' . var_export(__DIR__ . "/$file.sharp", true) . ';';
    echo strtok(trim(shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -r ' . escapeshellarg($code) . ' 2>&1')), "\n"), "\n";
}
?>
--EXPECTF--
Class Demo\Twice cannot extend both Lib\Entity and Lib\Other
Class Demo\Lost cannot inherit from Lib\Missing, which is neither a class, an interface nor a trait
Fatal error: Demo\Blend cannot implement Lib\Mixin - it is not an interface in %sHeaderNeither.sharp on line %d
Fatal error: Demo\Shade cannot implement Lib\Mixin - it is not an interface in %sHeaderEnumTrait.sharp on line %d
