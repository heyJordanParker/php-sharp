--TEST--
emit runs nothing when mago compile wrote no listener table, and refuses a table mago compile did not write, as the engine refuses a damaged compiled file
--FILE--
<?php
require __DIR__ . '/Container.inc';

$root = container_project('events-table', [
    'app/Demo/Paid.sharp' => "namespace App.Demo;\n\npublic event Paid(int orderId);\n",
    'app/Demo/Notices.sharp' => <<<'SHARP'
        namespace App.Demo;

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
$rows = substr($compiled, strpos($compiled, "'listeners' => ["));
$inputs = substr($compiled, 0, strpos($compiled, "'listeners' => ["));

// Each table runs in a process of its own, since a request reads its table once.
foreach ([
    'the compiled table' => $compiled,
    'no table' => null,
    'a table that is not a list' => '<?php return 1;',
    'a table without its listeners' => "<?php return ['inputs' => []];",
    'a row with three fields' => "<?php return ['inputs' => [], 'listeners' => [['App\\Demo\\Notices', 'hear', ['App\\Demo\\Paid']]]];",
    'a listed type that is not a name' => "<?php return ['inputs' => [], 'listeners' => [['App\\Demo\\Notices', 'hear', [1], true]]];",
    'a row that does not say whether the method takes the event' => "<?php return ['inputs' => [], 'listeners' => [['App\\Demo\\Notices', 'hear', ['App\\Demo\\Paid'], 1]]];",
    'an input with three fields' => "<?php return ['inputs' => [['app/Demo/*.sharp', 1, 0]], " . $rows,
    'an input whose hash is not 32 hex digits' => "<?php return ['inputs' => [['app/Demo/*.sharp', 1, 0, 'abc']], " . $rows,
    'a listener class that is gone' => $inputs . "'listeners' => [['App\\Demo\\Gone', 'hear', ['App\\Demo\\Paid'], true]]];",
    'a listener method that is gone' => $inputs . "'listeners' => [['App\\Demo\\Notices', 'gone', ['App\\Demo\\Paid'], true]]];",
] as $name => $contents) {
    $contents === null ? unlink($table) : file_put_contents($table, $contents);
    echo $name, ': ';
    echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -r ' . escapeshellarg(
        'require ' . var_export(__DIR__ . '/Container.inc', true) . '; container_load(' . var_export($root, true) . ');'
        . ' try { Sharp\Internal\emit(new App\Demo\Paid(7)); echo "heard ", App\Demo\Notices::$heard, "\n"; }'
        . ' catch (Error $e) { echo get_class($e), ": ", $e->getMessage(), "\n"; }'
    ));
}
remove_project($root);
?>
--EXPECT--
the compiled table: heard 7
no table: heard 0
a table that is not a list: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
a table without its listeners: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
a row with three fields: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
a listed type that is not a name: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
a row that does not say whether the method takes the event: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
an input with three fields: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
an input whose hash is not 32 hex digits: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
a listener class that is gone: Error: Class "App\Demo\Gone" not found
a listener method that is gone: Error: Call to undefined method App\Demo\Notices::gone()
