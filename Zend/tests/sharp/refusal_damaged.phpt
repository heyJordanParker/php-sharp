--TEST--
The engine refuses a compiled file whose length or offsets disagree with its header, as not compiled
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('damaged', ['Shop.sharp' => SHOP, 'Counter.sharp' => COUNTER]);
$compiled = "$root/.sharp/Shop.sharpc";
$bytes = file_get_contents($compiled);
['inputs' => $inputs, 'nodes' => $nodes] = unpack('Vinputs/Vnodes', $bytes, 80);
$first_node = 104 + 40 * $inputs;
$first_child = $first_node + 56 * $nodes;

foreach ([
    'one byte too long' => fn () => file_put_contents($compiled, $bytes . "\0"),
    'one byte too short' => fn () => file_put_contents($compiled, substr($bytes, 0, -1)),
    'shorter than a header' => fn () => file_put_contents($compiled, substr($bytes, 0, 100)),
    'a root past the nodes' => fn () => overwrite($compiled, 92, pack('V', $nodes)),
    'a child past the nodes' => fn () => overwrite($compiled, $first_child, pack('V', $nodes)),
    'a text past the texts' => fn () => overwrite($compiled, $first_node + 48, pack('V', 0x7fffffff)),
] as $damage => $write) {
    file_put_contents($compiled, $bytes);
    $write();
    echo $damage, ': ';
    refusal("$root/Shop.sharp");
}
?>
--CLEAN--
<?php
require __DIR__ . '/project.inc';
remove_project(sys_get_temp_dir() . '/sharp-test-damaged');
?>
--EXPECT--
one byte too long: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
one byte too short: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
shorter than a header: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
a root past the nodes: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
a child past the nodes: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
a text past the texts: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
