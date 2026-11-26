--TEST--
A process that started on a current compile runs to its end on it: a source saved after its first load leaves the next file it loads as it was compiled, with no refusal
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('save-during-run', ['Shop.sharp' => SHOP, 'Counter.sharp' => COUNTER]);
$code = 'require ' . var_export("$root/Shop.sharp", true) . '; echo "started\n";'
    . ' file_put_contents(' . var_export("$root/Counter.sharp", true) . ', "\n", FILE_APPEND);'
    . ' try { require ' . var_export("$root/Counter.sharp", true) . '; echo Demo\Shop::run(5), "\n"; }'
    . ' catch (CompileError $e) { echo get_class($e), ": ", $e->getMessage(), "\n"; }';

echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -r ' . escapeshellarg($code));
remove_project($root);
?>
--EXPECT--
started
5
