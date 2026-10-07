--TEST--
The engine refuses a .sharp file edited since its compile, naming the file itself
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('out-of-date-source', ['Shop.sharp' => SHOP, 'Counter.sharp' => COUNTER]);
file_put_contents("$root/Shop.sharp", SHOP . "\n");

refusal("$root/Shop.sharp");
?>
--CLEAN--
<?php
require __DIR__ . '/project.inc';
remove_project(sys_get_temp_dir() . '/sharp-test-out-of-date-source');
?>
--EXPECT--
CompileError: Shop.sharp is out of date (Shop.sharp changed). Run vendor/bin/mago compile.
