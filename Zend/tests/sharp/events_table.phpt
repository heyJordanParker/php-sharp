--TEST--
emit refuses a listener table mago compile did not write, as the engine refuses a damaged compiled file
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('events-table', [
    'Paid.sharp' => "namespace Demo;\n\npublic event Paid(int orderId);\n",
    'Notices.sharp' => <<<'SHARP'
        namespace Demo;

        public class Notices
        {
            public static int heard { get; set; } = 0;

            public void hear(Paid e) on Paid
            {
                Notices.heard = e.orderId;
            }
        }

        SHARP,
]);
$table = "$root/.sharp/listeners.php";
$compiled = file_get_contents($table);

// Each table runs in a process of its own, since a request reads its table once.
foreach ([
    'the compiled table' => $compiled,
    'no table' => null,
    'a table that is not a list' => '<?php return 1;',
    'a row with three fields' => "<?php return [['Demo\\Notices', 'hear', ['Demo\\Paid']]];",
    'a listed type that is not a name' => "<?php return [['Demo\\Notices', 'hear', [1], true]];",
    'a row that does not say whether the method takes the event' => "<?php return [['Demo\\Notices', 'hear', ['Demo\\Paid'], 1]];",
    'a listener class that is gone' => "<?php return [['Demo\\Gone', 'hear', ['Demo\\Paid'], true]];",
    'a listener method that is gone' => "<?php return [['Demo\\Notices', 'gone', ['Demo\\Paid'], true]];",
] as $name => $contents) {
    $contents === null ? unlink($table) : file_put_contents($table, $contents);
    echo $name, ': ';
    echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -r ' . escapeshellarg(
        'require ' . var_export("$root/Paid.sharp", true) . '; require ' . var_export("$root/Notices.sharp", true) . ';'
        . ' try { Sharp\Internal\emit(new Demo\Paid(7)); echo "heard ", Demo\Notices::$heard, "\n"; }'
        . ' catch (Error $e) { echo get_class($e), ": ", $e->getMessage(), "\n"; }'
    ));
}
remove_project($root);
?>
--EXPECT--
the compiled table: heard 7
no table: CompileError: .sharp/listeners.php is missing or damaged. Run vendor/bin/mago compile.
a table that is not a list: CompileError: .sharp/listeners.php is missing or damaged. Run vendor/bin/mago compile.
a row with three fields: CompileError: .sharp/listeners.php is missing or damaged. Run vendor/bin/mago compile.
a listed type that is not a name: CompileError: .sharp/listeners.php is missing or damaged. Run vendor/bin/mago compile.
a row that does not say whether the method takes the event: CompileError: .sharp/listeners.php is missing or damaged. Run vendor/bin/mago compile.
a listener class that is gone: Error: Class "Demo\Gone" not found
a listener method that is gone: Error: Call to undefined method Demo\Notices::gone()
