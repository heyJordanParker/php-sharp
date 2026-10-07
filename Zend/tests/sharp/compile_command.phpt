--TEST--
With sharp.compile_command set, the engine runs it in the project root once per request when a .sharp file isn't compiled or is out of date, and loads what it wrote
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('compile-command', ['Counter.sharp' => COUNTER]);
$settings = ['sharp.compile_command' => 'echo run >> runs.log && ' . escapeshellarg(getenv('TEST_MAGO_EXECUTABLE')) . ' --no-version-check compile'];

file_put_contents("$root/Counter.sharp", COUNTER . "\n");
file_put_contents("$root/Shop.sharp", SHOP);
refusal(["$root/Counter.sharp", "$root/Shop.sharp"], $settings);
echo file_get_contents("$root/runs.log");

file_put_contents("$root/Broken.sharp", "namespace Demo;\n\nclass Broken\n{\n    public int run() => \"text\";\n}\n");
refusal("$root/Broken.sharp", $settings);
echo file_get_contents("$root/runs.log");

// A file with no .sharp/ folder above it has no project to compile, so the command doesn't run.
$unrooted = sys_get_temp_dir() . '/sharp-test-compile-command-unrooted';
remove_project($unrooted);
mkdir($unrooted);
file_put_contents("$unrooted/Shop.sharp", SHOP);
refusal("$unrooted/Shop.sharp", ['sharp.compile_command' => 'echo run > ' . escapeshellarg("$unrooted/runs.log")]);
var_dump(file_exists("$unrooted/runs.log"));
?>
--CLEAN--
<?php
require __DIR__ . '/project.inc';
remove_project(sys_get_temp_dir() . '/sharp-test-compile-command');
remove_project(sys_get_temp_dir() . '/sharp-test-compile-command-unrooted');
?>
--EXPECTF--
ran
ran
run
CompileError: Broken.sharp isn't compiled. Run vendor/bin/mago compile.
run
run
CompileError: /%s/sharp-test-compile-command-unrooted/Shop.sharp isn't compiled. Run vendor/bin/mago compile.
bool(false)
