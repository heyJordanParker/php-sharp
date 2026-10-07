--TEST--
A PHP# class header that names a final class or an enum fails to link as PHP's extends does
--FILE--
<?php

// Each failure is fatal, so each file runs in its own process.
foreach (['HeaderFinal', 'HeaderEnum'] as $file) {
    $code = 'require ' . var_export(__DIR__ . '/harness/Header.inc', true) . '; require ' . var_export(__DIR__ . "/$file.sharp", true) . ';';
    echo strtok(trim(shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -r ' . escapeshellarg($code) . ' 2>&1')), "\n"), "\n";
}
?>
--EXPECTF--
Fatal error: Class Demo\Bound cannot extend final class Lib\Sealed in %sHeaderFinal.sharp on line %d
Fatal error: Class Demo\Suited cannot extend enum Lib\Suit in %sHeaderEnum.sharp on line %d
