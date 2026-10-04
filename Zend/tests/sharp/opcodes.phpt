--TEST--
PHP# compiles each .sharp fixture to the opcodes and lines of its PHP twin
--SKIPIF--
<?php
if (!getenv('TEST_PHPDBG_EXECUTABLE')) die('skip phpdbg is not built');
?>
--FILE--
<?php

function opcodes(string $file): string
{
    $command = escapeshellarg(getenv('TEST_PHPDBG_EXECUTABLE')) . ' -n -q -p* ' . escapeshellarg($file) . ' 2>&1';

    return str_replace($file, '<file>', shell_exec($command));
}

foreach (['Calc', 'Nulls', 'ControlFlow'] as $fixture) {
    $sharp = opcodes(__DIR__ . "/$fixture.sharp");
    $php = opcodes(__DIR__ . "/$fixture.inc");
    echo $fixture, ': ', $sharp === $php
        ? 'same opcodes and lines in ' . substr_count($sharp, '; (lines=') . ' op arrays'
        : "different opcodes\n.sharp:\n$sharp\n.php:\n$php", "\n";
}
?>
--EXPECT--
Calc: same opcodes and lines in 4 op arrays
Nulls: same opcodes and lines in 11 op arrays
ControlFlow: same opcodes and lines in 6 op arrays
