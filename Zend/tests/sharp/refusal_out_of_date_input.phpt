--TEST--
The engine refuses a .sharp file whose input went missing, naming the input, runs one whose input only got a new stamp or kept its declarations, and refuses the edited input itself until it compiles
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('out-of-date-input', ['Shop.sharp' => SHOP, 'Counter.sharp' => COUNTER]);

touch("$root/Counter.sharp", time() + 100);
refusal("$root/Shop.sharp");

file_put_contents("$root/Counter.sharp", COUNTER . "\n");
refusal(["$root/Shop.sharp", "$root/Counter.sharp"]);

unlink("$root/Counter.sharp");
refusal("$root/Shop.sharp");
remove_project($root);
?>
--EXPECT--
ran
ran
CompileError: Counter.sharp is out of date (Counter.sharp changed). Run vendor/bin/mago compile.
CompileError: Shop.sharp is out of date (Counter.sharp changed). Run vendor/bin/mago compile.
