--TEST--
The engine refuses a .sharp file with no .sharp folder above it, naming it by its real path
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = sys_get_temp_dir() . '/sharp-test-unrooted-' . getmypid();
remove_project($root);
mkdir($root);
file_put_contents("$root/Shop.sharp", SHOP);

refusal("$root/Shop.sharp");
remove_project($root);
?>
--EXPECTF--
CompileError: /%s/sharp-test-unrooted-%d/Shop.sharp isn't compiled. Run vendor/bin/mago compile.
