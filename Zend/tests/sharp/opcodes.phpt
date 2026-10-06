--TEST--
PHP# compiles each .sharp fixture to the opcodes, lines and signatures of its PHP twin
--FILE--
<?php

function compiled(string $file): string
{
    $opcodes = shell_exec(escapeshellarg(getenv('TEST_PHPDBG_EXECUTABLE')) . ' -n -q -p* ' . escapeshellarg($file) . ' 2>&1');
    $classes = shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -r ' . escapeshellarg(
        '$declared = get_declared_classes(); require ' . var_export($file, true) . ';'
        . ' foreach (array_diff(get_declared_classes(), $declared) as $class) echo new ReflectionClass($class);'
    ) . ' 2>&1');

    return str_replace($file, '<file>', $opcodes . $classes);
}

foreach (['Calc', 'Nulls', 'ControlFlow', 'Order', 'Product', 'Expressions', 'Collections', 'EnumKeys'] as $fixture) {
    $sharp = compiled(__DIR__ . "/$fixture.sharp");
    $php = compiled(__DIR__ . "/$fixture.inc");
    echo $fixture, ': ', $sharp === $php
        ? 'same opcodes and lines in ' . substr_count($sharp, '; (lines=') . ' op arrays,'
            . ' same signatures in ' . substr_count($sharp, 'Class [ <user> ') . ' classes'
        : "different\n.sharp:\n$sharp\n.php:\n$php", "\n";
}
?>
--EXPECT--
Calc: same opcodes and lines in 4 op arrays, same signatures in 1 classes
Nulls: same opcodes and lines in 10 op arrays, same signatures in 1 classes
ControlFlow: same opcodes and lines in 6 op arrays, same signatures in 1 classes
Order: same opcodes and lines in 4 op arrays, same signatures in 2 classes
Product: same opcodes and lines in 3 op arrays, same signatures in 1 classes
Expressions: same opcodes and lines in 6 op arrays, same signatures in 1 classes
Collections: same opcodes and lines in 7 op arrays, same signatures in 2 classes
EnumKeys: same opcodes and lines in 13 op arrays, same signatures in 1 classes
