--TEST--
The engine refuses a compiled file whose magic is not a .sharpc file's, as compiled for a different engine
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('different-engine', ['Shop.sharp' => SHOP, 'Counter.sharp' => COUNTER]);
overwrite("$root/.sharp/Shop.sharpc", 0, 'XHARPC');

refusal("$root/Shop.sharp");
?>
--CLEAN--
<?php
require __DIR__ . '/project.inc';
remove_project(sys_get_temp_dir() . '/sharp-test-different-engine');
?>
--EXPECT--
CompileError: Shop.sharp was compiled for a different PHP# engine. Install the mago-sharp release that matches this engine.
