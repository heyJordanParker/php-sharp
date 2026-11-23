--TEST--
emit runs nothing when mago compile wrote no listener table, refuses a table mago compile did not write, its effects and packages included, as the engine refuses a damaged compiled file, and says a table that lists a listener the code no longer has is out of date
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
    'a table without its listeners' => "<?php return ['inputs' => [], 'dispatcher' => null, 'packages' => [], 'attributes' => [], 'effects' => [], 'tags' => [], 'modules' => []];",
    'a table without its dispatcher' => "<?php return ['inputs' => [], 'listeners' => [], 'packages' => [], 'attributes' => [], 'effects' => [], 'tags' => [], 'modules' => []];",
    'a dispatcher that is not a namespace' => "<?php return ['inputs' => [], 'listeners' => [], 'dispatcher' => ['app/Module.sharp', 'app'], 'packages' => [], 'attributes' => [], 'effects' => [], 'tags' => [], 'modules' => []];",
    'a row with three fields' => "<?php return ['inputs' => [], 'listeners' => [['App\\Demo\\Notices', 'hear', ['App\\Demo\\Paid']]], 'dispatcher' => null, 'packages' => [], 'attributes' => [], 'effects' => [], 'tags' => [], 'modules' => []];",
    'a listed type that is not a name' => "<?php return ['inputs' => [], 'listeners' => [['App\\Demo\\Notices', 'hear', [1], true]], 'dispatcher' => null, 'packages' => [], 'attributes' => [], 'effects' => [], 'tags' => [], 'modules' => []];",
    'a row that does not say whether the method takes the event' => "<?php return ['inputs' => [], 'listeners' => [['App\\Demo\\Notices', 'hear', ['App\\Demo\\Paid'], 1]], 'dispatcher' => null, 'packages' => [], 'attributes' => [], 'effects' => [], 'tags' => [], 'modules' => []];",
    'an input with three fields' => "<?php return ['inputs' => [['app/Demo/*.sharp', 1, 0]], " . $rows,
    'an input whose hash is not 32 hex digits' => "<?php return ['inputs' => [['app/Demo/*.sharp', 1, 0, 'abc']], " . $rows,
    'a table without its packages' => "<?php return ['inputs' => [], 'listeners' => [], 'dispatcher' => null, 'attributes' => [], 'effects' => [], 'tags' => [], 'modules' => []];",
    'packages that are not folders' => "<?php return ['inputs' => [], 'listeners' => [], 'dispatcher' => null, 'packages' => [1], 'attributes' => [], 'effects' => [], 'tags' => [], 'modules' => []];",
    'a table without its attributes' => "<?php return ['inputs' => [], 'listeners' => [], 'dispatcher' => null, 'packages' => [], 'effects' => [], 'tags' => [], 'modules' => []];",
    'attributes that are not lists of paths' => "<?php return ['inputs' => [], 'listeners' => [], 'dispatcher' => null, 'packages' => [], 'attributes' => ['app\\demo\\paid' => [1]], 'effects' => [], 'tags' => [], 'modules' => []];",
    'a table without its effects' => "<?php return ['inputs' => [], 'listeners' => [], 'dispatcher' => null, 'packages' => [], 'attributes' => [], 'tags' => [], 'modules' => []];",
    'effects that are not lists of names' => "<?php return ['inputs' => [], 'listeners' => [], 'dispatcher' => null, 'packages' => [], 'attributes' => [], 'effects' => ['lib\\mailer' => [1]], 'tags' => [], 'modules' => []];",
    'a table without its tags' => "<?php return ['inputs' => [], 'listeners' => [], 'dispatcher' => null, 'packages' => [], 'attributes' => [], 'effects' => [], 'modules' => []];",
    'a family row that is not a key and its variants' => "<?php return ['inputs' => [], 'listeners' => [], 'dispatcher' => null, 'packages' => [], 'attributes' => [], 'effects' => [], 'tags' => ['App\\Demo\\Method' => ['type']], 'modules' => []];",
    'a table without its modules' => "<?php return ['inputs' => [], 'listeners' => [], 'dispatcher' => null, 'packages' => [], 'attributes' => [], 'effects' => [], 'tags' => []];",
    'modules that are not lists of paths' => "<?php return ['inputs' => [], 'listeners' => [], 'dispatcher' => null, 'packages' => [], 'attributes' => [], 'effects' => [], 'tags' => [], 'modules' => ['app' => [1]]];",
    'a listener class that is gone' => $inputs . "'listeners' => [['App\\Demo\\Gone', 'hear', ['App\\Demo\\Paid'], true]], 'dispatcher' => null, 'packages' => [], 'attributes' => [], 'effects' => [], 'tags' => [], 'modules' => []];",
    'a listener method that is gone' => $inputs . "'listeners' => [['App\\Demo\\Notices', 'gone', ['App\\Demo\\Paid'], true]], 'dispatcher' => null, 'packages' => [], 'attributes' => [], 'effects' => [], 'tags' => [], 'modules' => []];",
] as $name => $contents) {
    $contents === null ? unlink($table) : file_put_contents($table, $contents);
    echo $name, ': ';
    echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -r ' . escapeshellarg(
        'require ' . var_export(__DIR__ . '/Container.inc', true) . '; container_load(' . var_export($root, true) . ');'
        . ' try { Sharp\Internal\emit(new App\Demo\Paid(7), "Sharp\\\\Dispatcher"); echo "heard ", App\Demo\Notices::$heard, "\n"; }'
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
a table without its dispatcher: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
a dispatcher that is not a namespace: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
a row with three fields: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
a listed type that is not a name: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
a row that does not say whether the method takes the event: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
an input with three fields: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
an input whose hash is not 32 hex digits: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
a table without its packages: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
packages that are not folders: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
a table without its attributes: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
attributes that are not lists of paths: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
a table without its effects: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
effects that are not lists of names: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
a table without its tags: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
a family row that is not a key and its variants: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
a table without its modules: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
modules that are not lists of paths: CompileError: .sharp/listeners.php is damaged. Run vendor/bin/mago compile.
a listener class that is gone: CompileError: .sharp/listeners.php is out of date: it lists App.Demo.Gone, which no longer exists. Run vendor/bin/mago compile.
a listener method that is gone: CompileError: .sharp/listeners.php is out of date: it lists App.Demo.Notices.gone, which no longer exists. Run vendor/bin/mago compile.
