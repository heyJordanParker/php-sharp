--TEST--
A compile while a process runs switches .sharp/current to a new generation and keeps the old one the process holds, so the process finishes on the code it started on and a new one runs the new code, and the first compile after the process ends deletes the old generation
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('compile-during-run', ['Shop.sharp' => SHOP, 'Counter.sharp' => COUNTER]);
$old = realpath("$root/.sharp/current");
$shop = 'require ' . var_export("$root/Shop.sharp", true) . ';';
$counter = 'try { require ' . var_export("$root/Counter.sharp", true) . '; echo Demo\Shop::run(5), "\n"; }'
    . ' catch (CompileError $e) { echo get_class($e), ": ", $e->getMessage(), "\n"; }';
$php = escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -r ';

$running = proc_open($php . escapeshellarg("$shop echo \"started\\n\"; fgets(STDIN); $counter"), [0 => ['pipe', 'r'], 1 => ['pipe', 'w']], $pipes);
echo 'running: ', fgets($pipes[1]);

file_put_contents("$root/Counter.sharp", str_replace('this.count += amount;', 'this.count += amount * 10;', COUNTER));
run_mago($root);
echo 'current moved: ', var_export(realpath("$root/.sharp/current") !== $old, true), "\n";
echo 'old generation while the process holds it: ', var_export(is_dir($old), true), "\n";

fwrite($pipes[0], "\n");
echo 'running: ', stream_get_contents($pipes[1]);
fclose($pipes[0]);
fclose($pipes[1]);
proc_close($running);
echo 'new process: ', shell_exec($php . escapeshellarg("$shop $counter"));

run_mago($root);
echo 'old generation after the process ended: ', var_export(is_dir($old), true), "\n";
remove_project($root);
?>
--EXPECT--
running: started
current moved: true
old generation while the process holds it: true
running: 5
new process: 50
old generation after the process ended: false
