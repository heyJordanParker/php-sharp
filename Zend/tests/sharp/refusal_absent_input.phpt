--TEST--
The engine runs a .sharp file while an input that was absent at its compile stays absent, and refuses it, naming the input, once that input appears or a present one goes
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('absent-input', ['Shop.sharp' => SHOP, 'Counter.sharp' => COUNTER]);
refusal("$root/Shop.sharp");

file_put_contents("$root/composer.lock", "{}\n");
refusal("$root/Shop.sharp");

run_mago($root);
refusal("$root/Shop.sharp");

unlink("$root/composer.lock");
refusal("$root/Shop.sharp");
?>
--CLEAN--
<?php
require __DIR__ . '/project.inc';
remove_project(sys_get_temp_dir() . '/sharp-test-absent-input');
?>
--EXPECT--
ran
CompileError: Shop.sharp is out of date (composer.lock changed). Run vendor/bin/mago compile.
ran
CompileError: Shop.sharp is out of date (composer.lock changed). Run vendor/bin/mago compile.
