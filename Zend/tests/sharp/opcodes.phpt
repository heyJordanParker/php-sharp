--TEST--
PHP# compiles each .sharp fixture to the opcodes of its PHP twin
--FILE--
<?php

function opcodes(string $file): string
{
    $command = escapeshellarg(getenv('TEST_PHP_EXECUTABLE'))
        . ' -n -d opcache.enable_cli=1 -d opcache.file_update_protection=0 -d opcache.opt_debug_level=0x10000 -r '
        . escapeshellarg('require ' . var_export($file, true) . ';')
        . ' 2>&1';

    return str_replace($file, '<file>', shell_exec($command));
}

foreach (['Calc'] as $fixture) {
    $sharp = opcodes(__DIR__ . "/$fixture.sharp");
    $php = opcodes(__DIR__ . "/$fixture.inc");
    echo $fixture, ': ', $sharp === $php
        ? 'same opcodes in ' . substr_count($sharp, '(before optimizer)') . ' op arrays'
        : "different opcodes\n.sharp:\n$sharp\n.php:\n$php", "\n";
}
?>
--EXPECT--
Calc: same opcodes in 4 op arrays
