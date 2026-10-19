--TEST--
A PHP# method that is not virtual runs as a final one, so the engine refuses a plain PHP class that replaces it, static or not, as Kotlin emits a member that is not open final, and links one that replaces a virtual method
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('closed-methods', ['Base.sharp' => <<<'SHARP'
namespace Demo;

public class Base
{
    public Base() { }

    public void save() { }

    public virtual string title() => "base";

    public static string kind() => "base";
}

SHARP]);

$classes = [
    'OverridesClosed' => 'public function save(): void {}',
    'StaticToInstance' => 'public function kind(): string { return "instance"; }',
    'OverridesVirtual' => 'public function title(): string { return "virtual"; }',
];
foreach ($classes as $class => $method) {
    file_put_contents("$root/$class.php", "<?php\nnamespace Demo;\nclass $class extends Base\n{\n    $method\n}\n");
    $code = 'require ' . var_export("$root/Base.sharp", true) . '; require ' . var_export("$root/$class.php", true) . '; echo "' . $class . ' links\n";';
    $output = shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -d display_errors=1 -r ' . escapeshellarg($code) . ' 2>&1');
    echo strtok(trim($output), "\n"), "\n";
}

remove_project($root);
?>
--EXPECTF--
Fatal error: Cannot override final method Demo\Base::save() in %sOverridesClosed.php on line 5
Fatal error: Cannot override final method Demo\Base::kind() in %sStaticToInstance.php on line 5
OverridesVirtual links
