--TEST--
The engine refuses a .sharp file that has no compiled file, and names the fix
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('not-compiled', ['Counter.sharp' => COUNTER]);
file_put_contents("$root/Shop.sharp", SHOP);

refusal("$root/Shop.sharp");
?>
--CLEAN--
<?php
require __DIR__ . '/project.inc';
remove_project(sys_get_temp_dir() . '/sharp-test-not-compiled');
?>
--EXPECT--
CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
