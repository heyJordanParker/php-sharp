--TEST--
The engine refuses a .sharp file that has no compiled file, and names the fix
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('not-compiled', ['Counter.sharp' => COUNTER]);
file_put_contents("$root/Shop.sharp", SHOP);

refusal("$root/Shop.sharp");
remove_project($root);
?>
--EXPECT--
CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
