--TEST--
With sharp.compile_command set, the engine runs it in the project root once per request when a .sharp file isn't compiled or is out of date, loads what it wrote, and refuses a file the checker refused with the checker's errors
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('compile-command', ['Counter.sharp' => COUNTER]);
$settings = ['sharp.compile_command' => 'echo run >> runs.log && ' . escapeshellarg(getenv('TEST_MAGO_EXECUTABLE')) . ' --no-version-check compile'];

file_put_contents("$root/Counter.sharp", COUNTER . "\n");
file_put_contents("$root/Shop.sharp", SHOP);
refusal(["$root/Counter.sharp", "$root/Shop.sharp"], $settings);
echo file_get_contents("$root/runs.log");

// A file the checker refuses names its own errors from the command's report, which covers every file the command
// compiled. Its first error is the first in the file, whatever order the report lists them in.
file_put_contents("$root/Broken.sharp", "namespace Demo;\n\nclass Broken\n{\n    public int run() => \"text\";\n}\n");
file_put_contents("$root/Torn.sharp", "namespace Demo;\n\nclass Torn\n{\n    public int run( => 1;\n}\n");
file_put_contents("$root/Wrong.sharp", "namespace Demo;\n\nclass Wrong\n{\n    public int run() => \"text\";\n    public void go() { echo 1; }\n}\n");
refusal(["$root/Broken.sharp", "$root/Torn.sharp", "$root/Wrong.sharp"], $settings);
echo file_get_contents("$root/runs.log");

// A command that prints no checker report leaves the refusal as it was, and still runs once per request.
refusal(["$root/Broken.sharp", "$root/Torn.sharp"], ['sharp.compile_command' => 'echo run >> unreported.log && echo no report']);
echo file_get_contents("$root/unreported.log");

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
CompileError: Broken.sharp has an error on line 5: Invalid return type for function `Demo\Broken::run`: expected `int`, but found `string('text')`. Run vendor/bin/mago compile to see it.
CompileError: Torn.sharp has 2 errors. The first is on line 5: Parse error encountered during parsing. Run vendor/bin/mago compile to see them all.
CompileError: Wrong.sharp has 2 errors. The first is on line 5: Invalid return type for function `Demo\Wrong::run`: expected `int`, but found `string('text')`. Run vendor/bin/mago compile to see them all.
run
run
CompileError: Broken.sharp isn't compiled. Run vendor/bin/mago compile.
CompileError: Torn.sharp isn't compiled. Run vendor/bin/mago compile.
run
CompileError: /%s/sharp-test-compile-command-unrooted/Shop.sharp isn't compiled. Run vendor/bin/mago compile.
bool(false)
