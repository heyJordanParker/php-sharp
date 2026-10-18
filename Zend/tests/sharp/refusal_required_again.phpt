--TEST--
A .sharp file the engine refuses is never included, so requiring it again reports the same refusal
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = sys_get_temp_dir() . '/sharp-test-required-again-' . getmypid();
remove_project($root);
mkdir($root);
file_put_contents("$root/Shop.sharp", SHOP);

for ($attempt = 1; $attempt <= 2; $attempt++) {
    try {
        require_once "$root/Shop.sharp";
        echo "ran\n";
    } catch (CompileError $e) {
        echo get_class($e), ": ", $e->getMessage(), "\n";
    }
}
var_dump(in_array(realpath("$root/Shop.sharp"), get_included_files(), true));
remove_project($root);
?>
--EXPECTF--
CompileError: /%s/sharp-test-required-again-%d/Shop.sharp isn't compiled. Run vendor/bin/mago compile.
CompileError: /%s/sharp-test-required-again-%d/Shop.sharp isn't compiled. Run vendor/bin/mago compile.
bool(false)
