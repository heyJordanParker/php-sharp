--TEST--
A worker of a mago extension host, which mago starts with SHARP_ROOT and SHARP_COMPILED, loads the project's .sharp files from the folder outside the project that mago compiled them into for its hosts, with no .sharp folder in the project, and refuses a file that folder's report.json refuses with the checker's errors
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('host-compiled', ['Counter.sharp' => COUNTER, 'Shop.sharp' => SHOP]);
$folder = "$root-hosts";
remove_project($folder);
rename("$root/.sharp", $folder);
file_put_contents("$root/Broken.sharp", "namespace Demo;\n\nclass Broken\n{\n    public int run() => \"text\";\n}\n");
file_put_contents("$folder/report.json", json_encode(['issues' => [[
    'level' => 'Error',
    'message' => 'Invalid return type for method `Broken.run`: expected `int`, but found `string`.',
    'annotations' => [['kind' => 'Primary', 'span' => ['file_id' => ['path' => "$root/Broken.sharp"], 'start' => ['offset' => 50, 'line' => 4]]]],
]]]));

function load_as_host(array $paths, string $root, string $folder): void
{
    $code = '';
    foreach ($paths as $path) {
        $code .= 'try { require ' . var_export($path, true) . '; echo "ran\n"; }'
            . ' catch (CompileError $e) { echo get_class($e), ": ", $e->getMessage(), "\n"; }';
    }
    $code .= 'echo \Demo\Shop::run(2), "\n";';
    echo shell_exec('SHARP_ROOT=' . escapeshellarg($root) . ' SHARP_COMPILED=' . escapeshellarg($folder) . ' '
        . escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -r ' . escapeshellarg($code) . ' 2>&1');
}

load_as_host(["$root/Counter.sharp", "$root/Shop.sharp", "$root/Broken.sharp"], $root, $folder);

// Without the variables, the project has no .sharp folder, so nothing is compiled.
refusal("$root/Counter.sharp");

remove_project($root);
remove_project($folder);
?>
--EXPECTF--
ran
ran
CompileError: Broken.sharp has 1 error:
line 5: Invalid return type for method `Broken.run`: expected `int`, but found `string`.
2
CompileError: /%s/sharp-test-host-compiled-%d/Counter.sharp isn't compiled. Run vendor/bin/mago compile.
