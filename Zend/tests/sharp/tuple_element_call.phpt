--TEST--
A call of a tuple's named element runs the function the element holds, with the call's arguments
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('tuple-element-call', ['Probe/Entries.sharp' => <<<'SHARP'
namespace Probe;

public static class Entries
{
    public static int direct([Function<int(int)> twice, string name] entry) => entry.twice(2);

    public static int run() => Entries.direct([x => x * 2, "double"]);
}

SHARP]);
require "$root/Probe/Entries.sharp";
echo Probe\Entries::run(), "\n";
remove_project($root);
?>
--EXPECT--
4
