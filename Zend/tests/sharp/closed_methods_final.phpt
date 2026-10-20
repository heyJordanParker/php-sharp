--TEST--
A PHP# method that is not virtual stays closed to every class mago sees, so mago compile refuses a plain PHP class that replaces it, static or not, and links one that replaces a virtual method, while the engine runs it as an ordinary method, so a test double generated while tests run replaces it, as MockK replaces a Kotlin member that is not open
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('closed-methods', ['Base.sharp' => <<<'SHARP'
namespace Demo;

public class Base
{
    public Base() { }

    public string save() => "base saves";

    public virtual string title() => "base";

    public static string kind() => "base";
}

SHARP]);

$classes = [
    'OverridesClosed' => 'public function save(): string { return "replaced"; }',
    'StaticToInstance' => 'public function kind(): string { return "instance"; }',
    'OverridesVirtual' => 'public function title(): string { return "virtual"; }',
];
foreach ($classes as $class => $method) {
    file_put_contents("$root/$class.php", "<?php\nnamespace Demo;\nclass $class extends Base\n{\n    $method\n}\n");
}
file_put_contents("$root/mago.toml", "[source]\npaths = [\".\"]\nextensions = [\"sharp\", \"php\"]\n");
exec('MAGO_REPORTING_FORMAT=emacs ' . escapeshellarg(getenv('TEST_MAGO_EXECUTABLE')) . ' --no-version-check --colors never --workspace ' . escapeshellarg($root) . ' compile 2>&1', $output, $status);
echo "mago compile exits $status\n";
foreach ($output as $line) {
    if (preg_match('/^(\w+\.php):\d+:\d+:error - ([\w-]+): (.*)$/', $line, $match)) {
        echo "$match[1] $match[2]: $match[3]\n";
    }
}

// A test double, as PHPUnit's createMock or Mockery generates one while the tests run.
$code = 'require ' . var_export("$root/Base.sharp", true) . ';'
    . ' eval(\'namespace Demo; class BaseDouble extends Base { public function save(): string { return "the double saves"; } public static function kind(): string { return "double"; } }\');'
    . ' echo (new Demo\BaseDouble())->save(), "\n", Demo\BaseDouble::kind(), "\n";';
echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -d display_errors=1 -r ' . escapeshellarg($code) . ' 2>&1');

remove_project($root);
?>
--EXPECT--
mago compile exits 1
OverridesClosed.php override-final-method: `Demo\Base::save()` is not `virtual`, so no subclass replaces it.
StaticToInstance.php override-final-method: `Demo\Base::kind()` is not `virtual`, so no subclass replaces it.
the double saves
double
