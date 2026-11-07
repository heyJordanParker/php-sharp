--TEST--
With sharp.compile_command set, the engine runs it in the project root once per request when a .sharp file isn't compiled or is out of date, loads what it wrote, and refuses a file the checker refused with every one of its errors, in the order they appear in the file. It never runs it inside a compile, and a refusal after a command that failed says how it ended
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('compile-command', ['Counter.sharp' => COUNTER]);
$settings = ['sharp.compile_command' => 'echo run >> runs.log && ' . escapeshellarg(getenv('TEST_MAGO_EXECUTABLE')) . ' --no-version-check compile'];

file_put_contents("$root/Counter.sharp", COUNTER . "\n");
file_put_contents("$root/Shop.sharp", SHOP);
refusal(["$root/Counter.sharp", "$root/Shop.sharp"], $settings);
echo file_get_contents("$root/runs.log");

// A file the checker refuses lists its own errors from the command's report, which covers every file the command
// compiled. They follow the order of the file, whatever order the report lists them in: Wrong.sharp's report lists the
// later error on line 5 first, and Order.sharp's lists line 19 before lines 12 and 31.
file_put_contents("$root/Broken.sharp", "namespace Demo;\n\nclass Broken\n{\n    public int run() => \"text\";\n}\n");
file_put_contents("$root/Torn.sharp", "namespace Demo;\n\nclass Torn\n{\n    public int run( => 1;\n}\n");
file_put_contents("$root/Wrong.sharp", <<<'SHARP'
namespace Demo;

class Wrong
{
    public void run() { echo 1; } public void run() {}
}

SHARP);
mkdir("$root/app/Orders", recursive: true);
file_put_contents("$root/app/Orders/Order.sharp", <<<'SHARP'
namespace App.Orders;

class Order
{
    private int count = 0;

    public void add(int amount)
    {
        this.count += amount;
    }

    public int total() => "text";

    public int size()
    {
        return this.count;
    }

    public void show() { echo this.count; }

    public int first()
    {
        return this.count;
    }

    public int last()
    {
        return this.count;
    }

    public string label() => 1;
}

SHARP);
refusal(["$root/Broken.sharp", "$root/Torn.sharp", "$root/Wrong.sharp", "$root/app/Orders/Order.sharp"], $settings);
echo file_get_contents("$root/runs.log");

// A command that prints no checker report leaves the refusal as it was, and still runs once per request.
refusal(["$root/Broken.sharp", "$root/Torn.sharp"], ['sharp.compile_command' => 'echo run >> unreported.log && echo no report']);
echo file_get_contents("$root/unreported.log");

// The command runs with SHARP_COMPILING=1, and a process that runs with it set, as the command and every process mago
// starts do, never runs the command, so a compile never starts another.
refusal("$root/Torn.sharp", ['sharp.compile_command' => 'echo compiling=$SHARP_COMPILING >> marked.log']);
echo file_get_contents("$root/marked.log");
putenv('SHARP_COMPILING=1');
refusal("$root/Torn.sharp", ['sharp.compile_command' => 'echo run >> nested.log']);
putenv('SHARP_COMPILING');
var_dump(file_exists("$root/nested.log"));

// A command that fails gives the refusal how it ended and its last three lines of stderr, of its last 1,024 bytes.
refusal("$root/Torn.sharp", ['sharp.compile_command' => 'for n in 1 2 3 4 5; do echo line $n >&2; done; exit 3']);
refusal("$root/Torn.sharp", ['sharp.compile_command' => './no-such-mago compile']);
refusal("$root/Torn.sharp", ['sharp.compile_command' => 'kill -9 $$']);
ob_start();
refusal("$root/Torn.sharp", ['sharp.compile_command' => 'printf %3000s | tr -c x x >&2; echo >&2; echo end >&2; exit 1']);
echo preg_replace_callback('/x{2,}/', fn (array $run) => 'x * ' . strlen($run[0]), ob_get_clean());

// A file with no .sharp/ folder above it has no project to compile, so the command doesn't run.
$unrooted = sys_get_temp_dir() . '/sharp-test-compile-command-unrooted-' . getmypid();
remove_project($unrooted);
mkdir($unrooted);
file_put_contents("$unrooted/Shop.sharp", SHOP);
refusal("$unrooted/Shop.sharp", ['sharp.compile_command' => 'echo run > ' . escapeshellarg("$unrooted/runs.log")]);
var_dump(file_exists("$unrooted/runs.log"));
remove_project($root);
remove_project($unrooted);
?>
--EXPECTF--
ran
ran
run
CompileError: Broken.sharp has 1 error:
line 5: Invalid return type for method `Broken.run`: expected `int`, but found `string`.
  The type `string` returned here is not compatible with the declared return type `int`.
  help: Change the return value to match `int`, or update the method's return type declaration.
CompileError: Torn.sharp has 1 error:
line 5: Unexpected `=>`
CompileError: Wrong.sharp has 2 errors:
line 5: Unexpected `echo`
line 5: `Wrong.run` is declared twice: a class has one method per name, with no overloading by argument types.
  help: Give each a name that says what it takes, as in `grantUserAccess` and `grantTenantAccess`.
CompileError: app/Orders/Order.sharp has 3 errors:
line 12: Invalid return type for method `Order.total`: expected `int`, but found `string`.
  The type `string` returned here is not compatible with the declared return type `int`.
  help: Change the return value to match `int`, or update the method's return type declaration.
line 19: Unexpected `echo`
line 31: Invalid return type for method `Order.label`: expected `string`, but found `int`.
  The type `int` returned here is not compatible with the declared return type `string`.
  help: Change the return value to match `string`, or update the method's return type declaration.
run
run
CompileError: Broken.sharp isn't compiled. Run vendor/bin/mago compile.
CompileError: Torn.sharp isn't compiled. Run vendor/bin/mago compile.
run
CompileError: Torn.sharp isn't compiled. Run vendor/bin/mago compile.
compiling=1
CompileError: Torn.sharp isn't compiled. Run vendor/bin/mago compile.
bool(false)
CompileError: Torn.sharp isn't compiled. Run vendor/bin/mago compile. `for n in 1 2 3 4 5; do echo line $n >&2; done; exit 3` failed with exit 3: line 3
  line 4
  line 5
CompileError: Torn.sharp isn't compiled. Run vendor/bin/mago compile. `./no-such-mago compile` failed with exit 127: %s
CompileError: Torn.sharp isn't compiled. Run vendor/bin/mago compile. `kill -9 $$` was killed by signal 9
CompileError: Torn.sharp isn't compiled. Run vendor/bin/mago compile. `printf %3000s | tr -c x x >&2; echo >&2; echo end >&2; exit 1` failed with exit 1: x * 1019
  end
CompileError: /%s/sharp-test-compile-command-unrooted-%d/Shop.sharp isn't compiled. Run vendor/bin/mago compile.
bool(false)
