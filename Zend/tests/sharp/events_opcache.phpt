--TEST--
With sharp.compile_command set, emit reads the listener table past opcache, so a request whose compile ran before its first emit runs the listeners that compile added
--EXTENSIONS--
opcache
--FILE--
<?php
require __DIR__ . '/Container.inc';

$root = container_project('events-opcache', [
    'app/Fresh/Paid.sharp' => "namespace App.Fresh;\n\npublic event Paid(int orderId);\n",
    'app/Fresh/Notices.sharp' => <<<'SHARP'
        namespace App.Fresh;

        public class Notices
        {
            public static List<string> log { get; set; } = [];

            public void hear(Paid e) on Paid { Notices.log.add("hear"); }
        }

        SHARP,
]);
$compile = 'echo run >> runs.log && ' . escapeshellarg(getenv('TEST_MAGO_EXECUTABLE')) . ' --no-version-check compile > /dev/null';
$audit = "namespace App.Fresh;\n\npublic class Audit\n{\n    public void record() on Paid { Notices.log.add(\"audit\"); }\n}\n";
$warmup = "namespace App.Fresh;\n\npublic class Warmup\n{\n}\n";

// One process: opcache holds the table, a listener file and a new class appear, and loading the class runs the
// compile before the first emit.
echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n'
    . ' -d opcache.enable=1 -d opcache.enable_cli=1 -d opcache.validate_timestamps=1'
    . ' -d opcache.revalidate_freq=60 -d opcache.file_update_protection=0'
    . ' -d ' . escapeshellarg("sharp.compile_command=\"$compile\"")
    . ' -r ' . escapeshellarg(
        'require ' . var_export(__DIR__ . '/Container.inc', true) . '; container_load(' . var_export($root, true) . ');'
        . ' echo "table cached: ", var_export(opcache_compile_file(' . var_export("$root/.sharp/listeners.php", true) . '), true), "\n";'
        . ' file_put_contents(' . var_export("$root/app/Fresh/Audit.sharp", true) . ', ' . var_export($audit, true) . ');'
        . ' file_put_contents(' . var_export("$root/app/Fresh/Warmup.sharp", true) . ', ' . var_export($warmup, true) . ');'
        . ' echo "Warmup loaded: ", var_export(class_exists("App\\\\Fresh\\\\Warmup"), true), "\n";'
        . ' Sharp\Internal\emit(new App\Fresh\Paid(1));'
        . ' echo implode(", ", App\Fresh\Notices::$log), "; compiles: ", substr_count(file_get_contents(' . var_export("$root/runs.log", true) . '), "run"), "\n";'
    ));
remove_project($root);
?>
--EXPECT--
table cached: true
Warmup loaded: true
audit, hear; compiles: 1
