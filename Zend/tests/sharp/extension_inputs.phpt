--TEST--
A compiled file whose call reaches an extension is out of date when a .sharp file of its namespace's folder appears, changes or goes, since any of them can declare an extension that shadows the one it calls, and a compiled file that calls no extension stays current
--FILE--
<?php
require __DIR__ . '/project.inc';

const WORDS = "namespace Sharp.Text;\n\npublic static class Words\n{\n    extension(string text)\n    {\n        public string shout() => \"default\";\n    }\n}\n";
const REPORT = "namespace App;\n\npublic class Report\n{\n    public static string text(string name) => name.shout();\n}\n";
const PLAIN = "namespace App;\n\npublic class Plain\n{\n    public static string text(string name) => name;\n}\n";
const HELPERS = "namespace App;\n\npublic static class Helpers\n{\n    public static int one() => 1;\n}\n";
const LOUD = "namespace App;\n\npublic static class Loud\n{\n    extension(string text)\n    {\n        public string shout() => \"loud\";\n    }\n}\n";

$root = compile_project('extension-inputs', []);
mkdir("$root/Sharp/Text", recursive: true);
mkdir("$root/App");
file_put_contents("$root/Sharp/Text/Words.sharp", WORDS);
file_put_contents("$root/App/Report.sharp", REPORT);
file_put_contents("$root/App/Plain.sharp", PLAIN);
file_put_contents("$root/App/Helpers.sharp", HELPERS);
run_mago($root);

$compile = 'echo compiled >> runs.log && ' . escapeshellarg(getenv('TEST_MAGO_EXECUTABLE')) . ' --no-version-check compile';

// Each run loads the files in one process of its own, and Report before any file that is new, so the compile command
// that a new file's own load runs comes too late to rewrite a Report the engine already loaded.
function run(string $root, string $compile, array $files, string $class): void
{
    @unlink("$root/runs.log");
    $code = '';
    foreach ($files as $file) {
        $code .= 'require ' . var_export("$root/$file", true) . ';';
    }
    $code .= "echo \\$class::text('x'), \"\\n\";";
    echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -d ' . escapeshellarg("sharp.compile_command=\"$compile\"") . ' -r ' . escapeshellarg($code));
    echo file_exists("$root/runs.log") ? "compiled\n" : "current\n";
}

echo "== as compiled\n";
run($root, $compile, ['Sharp/Text/Words.sharp', 'App/Report.sharp'], 'App\Report');

echo "== a new file of the namespace declares a shadowing extension\n";
file_put_contents("$root/App/Loud.sharp", LOUD);
run($root, $compile, ['Sharp/Text/Words.sharp', 'App/Report.sharp', 'App/Loud.sharp'], 'App\Report');

echo "== the file is deleted\n";
unlink("$root/App/Loud.sharp");
run($root, $compile, ['Sharp/Text/Words.sharp', 'App/Report.sharp'], 'App\Report');

echo "== an existing file of the namespace gains the extension block\n";
file_put_contents("$root/App/Helpers.sharp", str_replace("public static int one() => 1;", "public static int one() => 1;\n\n    extension(string text)\n    {\n        public string shout() => \"helper\";\n    }", HELPERS));
run($root, $compile, ['Sharp/Text/Words.sharp', 'App/Report.sharp', 'App/Helpers.sharp'], 'App\Report');

echo "== a file that calls no extension, after its folder gains a file\n";
file_put_contents("$root/App/Extra.sharp", "namespace App;\n\npublic class Extra\n{\n}\n");
run($root, $compile, ['App/Plain.sharp'], 'App\Plain');

remove_project($root);
?>
--EXPECT--
== as compiled
default
current
== a new file of the namespace declares a shadowing extension
loud
compiled
== the file is deleted
default
compiled
== an existing file of the namespace gains the extension block
helper
compiled
== a file that calls no extension, after its folder gains a file
x
current
