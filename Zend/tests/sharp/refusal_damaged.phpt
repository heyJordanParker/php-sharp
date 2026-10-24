--TEST--
The engine refuses a compiled file whose length, offsets or sections disagree with its header, or whose tree it can't translate, as not compiled
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('damaged', ['Shop.sharp' => SHOP, 'Counter.sharp' => COUNTER]);
$compiled = "$root/.sharp/Shop.sharpc";
$bytes = file_get_contents($compiled);
['inputs' => $inputs, 'nodes' => $nodes, 'children' => $children, 'root' => $top] = unpack('Vinputs/Vnodes/Vchildren/Vroot', $bytes, 80);
$first_node = 144 + 40 * $inputs;
$first_child = $first_node + 56 * $nodes;
$texts = $first_child + 4 * $children;
// The fields of node $index the cases change: its kind, its children, its value's tag and its text.
$node = fn (int $index) => unpack('vkind/x14/Vfirst_child/Vchild_count/Cvalue/x23/Voffset/Vlen', $bytes, $first_node + 56 * $index);
$first = fn (callable $holds) => array_find(range(0, $nodes - 1), fn (int $index) => $holds($node($index)));
$demo = $first(fn (array $node) => $node['kind'] === 64 && $node['value'] === 5 && substr($bytes, $texts + $node['offset'], $node['len']) === 'Demo');
$value = $first(fn (array $node) => $node['kind'] === 64);
$return = $first(fn (array $node) => $node['kind'] === 278);
echo $children >= 7 ? '' : "the file holds too few children for the kind case\n";

function refused(string $damage, callable $write): void
{
    global $root, $compiled, $bytes;
    file_put_contents($compiled, $bytes);
    $write();
    echo $damage, ': ';
    refusal("$root/Shop.sharp");
}

foreach ([
    'one byte too long' => fn () => file_put_contents($compiled, $bytes . "\0"),
    'one byte too short' => fn () => file_put_contents($compiled, substr($bytes, 0, -1)),
    'shorter than a header' => fn () => file_put_contents($compiled, substr($bytes, 0, 100)),
    'a root past the nodes' => fn () => overwrite($compiled, 92, pack('V', $nodes)),
    'a library version past the texts' => fn () => overwrite($compiled, 116, pack('V', 0x7fffffff)),
    // The namespace's name "Demo" becomes the int 0x4141414141414141, which zend_compile would read as a string.
    'sections changed after mago wrote them' => function () use ($compiled, $first_node, $demo) {
        overwrite($compiled, $first_node + 56 * $demo + 24, chr(3));
        overwrite($compiled, $first_node + 56 * $demo + 32, pack('P', 0x4141414141414141));
    },
] as $damage => $write) {
    refused($damage, $write);
}

// Each tree below gets the hash of its sections again, so the engine reads the tree the case changed.
foreach ([
    'a child past the nodes' => fn () => overwrite($compiled, $first_child, pack('V', $nodes)),
    'a text past the texts' => fn () => overwrite($compiled, $first_node + 48, pack('V', 0x7fffffff)),
    // php-src's kinds take at most 6 children, and 0x0701 would take 7.
    'a kind php-src does not declare' => function () use ($compiled, $first_node, $top) {
        overwrite($compiled, $first_node + 56 * $top, pack('v', 0x0701));
        overwrite($compiled, $first_node + 56 * $top + 16, pack('VV', 0, 7));
    },
    'a return with two children' => fn () => overwrite($compiled, $first_node + 56 * $return + 20, pack('V', 2)),
    'a statement list that holds itself' => fn () => overwrite($compiled, $first_child + 4 * $node($top)['first_child'], pack('V', $top)),
    'a value tag past SHARP_STRING' => fn () => overwrite($compiled, $first_node + 56 * $value + 24, chr(9)),
] as $damage => $write) {
    refused($damage, function () use ($write, $compiled) {
        $write();
        rehash_sections($compiled);
    });
}

refused('the file as mago wrote it', fn () => null);
remove_project($root);
?>
--EXPECT--
one byte too long: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
one byte too short: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
shorter than a header: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
a root past the nodes: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
a library version past the texts: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
sections changed after mago wrote them: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
a child past the nodes: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
a text past the texts: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
a kind php-src does not declare: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
a return with two children: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
a statement list that holds itself: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
a value tag past SHARP_STRING: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
the file as mago wrote it: ran
