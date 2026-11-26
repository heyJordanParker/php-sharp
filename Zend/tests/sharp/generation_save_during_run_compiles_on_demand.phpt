--TEST--
With sharp.compile_command set, every load is checked as it loads: a source saved after the process's first load makes that file stale when it loads, and the request compiles it then and runs the new compile
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('save-during-run-on-demand', ['Shop.sharp' => SHOP, 'Counter.sharp' => COUNTER]);
$old = realpath("$root/.sharp/current");
$saved = str_replace('this.count += amount;', 'this.count += amount * 10;', COUNTER);
$code = 'require ' . var_export("$root/Shop.sharp", true) . '; echo "started\n";'
    . ' file_put_contents(' . var_export("$root/Counter.sharp", true) . ', ' . var_export($saved, true) . ');'
    . ' try { require ' . var_export("$root/Counter.sharp", true) . '; echo Demo\Shop::run(5), "\n"; }'
    . ' catch (CompileError $e) { echo get_class($e), ": ", $e->getMessage(), "\n"; }';
$command = escapeshellarg(getenv('TEST_MAGO_EXECUTABLE')) . ' --no-version-check compile';
$process = proc_open(
    [getenv('TEST_PHP_EXECUTABLE'), '-n', '-d', "sharp.compile_command=\"$command\"", '-r', $code],
    [1 => ['pipe', 'w']],
    $pipes,
    $root,
);
echo stream_get_contents($pipes[1]);
fclose($pipes[1]);
proc_close($process);
clearstatcache(true);
echo 'current moved: ', var_export(realpath("$root/.sharp/current") !== $old, true), "\n";
remove_project($root);
?>
--EXPECT--
started
50
current moved: true
