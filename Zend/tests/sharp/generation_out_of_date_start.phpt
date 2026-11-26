--TEST--
A process that starts after an edit no compile has seen loads each file whose inputs are as compiled, and refuses the first file it loads whose input changed, naming the change
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('out-of-date-start', ['Shop.sharp' => SHOP, 'Counter.sharp' => COUNTER]);
file_put_contents("$root/Counter.sharp", "\n", FILE_APPEND);

refusal(["$root/Shop.sharp", "$root/Counter.sharp"]);
remove_project($root);
?>
--EXPECT--
ran
CompileError: Counter.sharp is out of date (Counter.sharp changed). Run vendor/bin/mago compile.
