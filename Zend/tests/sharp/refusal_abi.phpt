--TEST--
The engine refuses a compiled file written for another SHARP_UNIT_ABI, as compiled for a different engine
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('abi', ['Shop.sharp' => SHOP, 'Counter.sharp' => COUNTER]);
$compiled = "$root/.sharp/Shop.sharpc";
overwrite($compiled, 8, chr(ord(file_get_contents($compiled)[8]) ^ 0xff));

refusal("$root/Shop.sharp");
remove_project($root);
?>
--EXPECT--
CompileError: Shop.sharp was compiled for a different PHP# engine. Install the mago-sharp release that matches this engine.
