--TEST--
With sharp.compile_command set, emit runs it when a PHP# file changed, or a PHP# file or a namespace folder joined or left a source folder, since mago compile wrote the listener table; without it, emit reads the table alone
--FILE--
<?php
require __DIR__ . '/Container.inc';

$root = container_project('events-fresh', [
    'app/Fresh/Paid.sharp' => "namespace App.Fresh;\n\npublic event Paid(int orderId);\n",
    'app/Fresh/Notices.sharp' => <<<'SHARP'
        namespace App.Fresh;

        public class Notices
        {
            public static List<string> log { get; set; } = [];

            public void hear(Paid e) on Paid { Notices.log.add("hear"); }
        }

        SHARP,
    'app/Fresh/Quiet.sharp' => <<<'SHARP'
        namespace App.Fresh;

        public class Quiet
        {
            public void record() { Notices.log.add("quiet"); }
        }

        SHARP,
]);
$compile = 'echo run >> runs.log && ' . escapeshellarg(getenv('TEST_MAGO_EXECUTABLE')) . ' --no-version-check compile > /dev/null';

// Each request runs in a process of its own, since a request reads its table once.
function request(string $root, ?string $compile): void
{
    $setting = $compile === null ? '' : ' -d ' . escapeshellarg("sharp.compile_command=\"$compile\"");
    echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . " -n$setting -r " . escapeshellarg(
        'require ' . var_export(__DIR__ . '/Container.inc', true) . '; container_load(' . var_export($root, true) . ');'
        . ' Sharp\Internal\emit(new App\Fresh\Paid(1));'
        . ' echo implode(", ", App\Fresh\Notices::$log), "; compiles: ", substr_count(@file_get_contents(' . var_export("$root/runs.log", true) . ') ?: "", "run"), "\n";'
    ));
}

echo 'nothing changed: ';
request($root, $compile);

file_put_contents("$root/app/Fresh/Audit.sharp", <<<'SHARP'
    namespace App.Fresh;

    public class Audit
    {
        public void record() on Paid { Notices.log.add("audit"); }
    }

    SHARP);
echo 'a new listener file, without the compile command: ';
request($root, null);
echo 'a new listener file, with it: ';
request($root, $compile);

file_put_contents("$root/app/Fresh/Notices.sharp", str_replace(
    'public void hear(Paid e) on Paid { Notices.log.add("hear"); }',
    "public void hear(Paid e) on Paid { Notices.log.add(\"hear\"); }\n\n    public void count() on Paid { Notices.log.add(\"count\"); }",
    file_get_contents("$root/app/Fresh/Notices.sharp"),
));
echo 'a listener added to a listener\'s file: ';
request($root, $compile);

unlink("$root/app/Fresh/Audit.sharp");
echo 'a listener file deleted: ';
request($root, $compile);

mkdir("$root/app/Fresh/Billing");
file_put_contents("$root/app/Fresh/Billing/Ledger.sharp", <<<'SHARP'
    namespace App.Fresh.Billing;

    import App.Fresh.Notices;
    import App.Fresh.Paid;

    public class Ledger
    {
        public void record() on Paid { Notices.log.add("ledger"); }
    }

    SHARP);
echo 'a listener in a new namespace folder: ';
request($root, $compile);

file_put_contents("$root/app/Fresh/Quiet.sharp", str_replace(
    'public void record() {',
    'public void record() on Paid {',
    file_get_contents("$root/app/Fresh/Quiet.sharp"),
));
echo 'an on clause added to a class that had none and that nothing loads: ';
request($root, $compile);

echo 'nothing changed since: ';
request($root, $compile);
remove_project($root);
?>
--EXPECT--
nothing changed: hear; compiles: 0
a new listener file, without the compile command: hear; compiles: 0
a new listener file, with it: audit, hear; compiles: 1
a listener added to a listener's file: audit, hear, count; compiles: 2
a listener file deleted: hear, count; compiles: 3
a listener in a new namespace folder: ledger, hear, count; compiles: 4
an on clause added to a class that had none and that nothing loads: ledger, hear, count, quiet; compiles: 5
nothing changed since: ledger, hear, count, quiet; compiles: 5
