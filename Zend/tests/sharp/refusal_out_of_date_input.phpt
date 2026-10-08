--TEST--
The engine refuses a .sharp file whose input changed or went missing, naming the input, and runs one whose input only got a new stamp
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('out-of-date-input', ['Shop.sharp' => SHOP, 'Counter.sharp' => COUNTER]);

touch("$root/Counter.sharp", time() + 100);
refusal("$root/Shop.sharp");

file_put_contents("$root/Counter.sharp", COUNTER . "\n");
refusal("$root/Shop.sharp");

unlink("$root/Counter.sharp");
refusal("$root/Shop.sharp");
?>
--CLEAN--
<?php
require __DIR__ . '/project.inc';
remove_project(sys_get_temp_dir() . '/sharp-test-out-of-date-input');
?>
--EXPECT--
ran
CompileError: Shop.sharp is out of date (Counter.sharp changed). Run vendor/bin/mago compile.
CompileError: Shop.sharp is out of date (Counter.sharp changed). Run vendor/bin/mago compile.
