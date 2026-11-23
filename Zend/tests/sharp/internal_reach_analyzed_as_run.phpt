--TEST--
mago analyze refuses plain PHP's reach of what the engine holds to a namespace exactly where the engine refuses it when it runs, spec section 5, in the engine's words and naming the caller the engine names: a class or a function outside the namespace calling an internal method, reading an internal constant, writing through an internal set, creating an internal class or calling a public method an internal enum declares, and top-level code or a closure outside any class inside the namespace doing so, while from and tryFrom, which replace BackedEnum's wider members, and a class and a function of the namespace reach every one
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('internal-reach-analyzed-as-run', [
    'Demo/Store/Catalog.sharp' => <<<'SHARP'
namespace Demo.Store;

public final class Catalog
{
    internal const int LIMIT = 10;

    public int views { get; internal set; } = 0;

    internal string secret() => "secret";
}

SHARP,
    'Demo/Store/Till.sharp' => <<<'SHARP'
namespace Demo.Store;

final class Till
{
}

SHARP,
    'Demo/Store/Status.sharp' => <<<'SHARP'
namespace Demo.Store;

enum Status : string
{
    case Active = "a";

    public string label() => this.name;
}

SHARP,
]);

$plain = [
    'Demo/Outside/Caller.php' => <<<'PHP'
<?php
namespace Demo\Outside;

use Demo\Store\Catalog;
use Demo\Store\Status;
use Demo\Store\Till;

final class Caller
{
    public static function secret(): string { return (new Catalog())->secret(); }
    public static function limit(): int { return Catalog::LIMIT; }
    public static function views(): void { $catalog = new Catalog(); $catalog->views = 2; }
    public static function till(): Till { return new Till(); }
    public static function label(): string { return Status::from('a')->label(); }
    public static function from(): string { return Status::from('a')->name . Status::tryFrom('a')?->name; }
}

PHP,
    'Demo/Outside/functions.php' => <<<'PHP'
<?php
namespace Demo\Outside;

function secret(): string { return (new \Demo\Store\Catalog())->secret(); }

PHP,
    'Demo/Store/boot.php' => <<<'PHP'
<?php
namespace Demo\Store;

echo (new Catalog())->secret();

PHP,
    'Demo/Store/closure.php' => <<<'PHP'
<?php
namespace Demo\Store;

return static fn (): string => (new Catalog())->secret();

PHP,
    'Demo/Store/Shelf.php' => <<<'PHP'
<?php
namespace Demo\Store;

final class Shelf
{
    public static function all(): string
    {
        new Till();
        $catalog = new Catalog();
        $catalog->views = 2;

        return $catalog->secret() . Catalog::LIMIT . Status::from('a')->label();
    }
}

PHP,
    'Demo/Store/functions.php' => <<<'PHP'
<?php
namespace Demo\Store;

function shelve(): string { return (new Catalog())->secret() . Catalog::LIMIT; }

PHP,
];
foreach ($plain as $path => $contents) {
    is_dir(dirname("$root/$path")) || mkdir(dirname("$root/$path"), recursive: true);
    file_put_contents("$root/$path", $contents);
}
file_put_contents("$root/mago.toml", "[source]\npaths = [\".\"]\nextensions = [\"sharp\", \"php\"]\n");
exec(escapeshellarg(getenv('TEST_MAGO_EXECUTABLE')) . ' --no-version-check --colors never --workspace ' . escapeshellarg($root) . ' analyze 2>&1', $output, $status);
echo "mago analyze exits $status\n";

// Each reach mago refuses, by its file and line, as the engine's Error reads: no backticks and no closing period.
$refused = [];
foreach ($output as $line) {
    if (preg_match('/^([\w\/]+\.php):(\d+):\d+:error - internal-access: (.*)$/', $line, $match)) {
        $refused["$match[1]:$match[2]"][] = rtrim(str_replace('`', '', $match[3]), '.');
    }
}

$runner = <<<'PHP'
<?php
foreach (['Catalog', 'Till', 'Status'] as $class) {
    require __DIR__ . "/Demo/Store/$class.sharp";
}
foreach (['Outside/Caller', 'Outside/functions', 'Store/Shelf', 'Store/functions'] as $file) {
    require __DIR__ . "/Demo/$file.php";
}

$calls = [
    'a class outside calls an internal method' => fn () => Demo\Outside\Caller::secret(),
    'a class outside reads an internal constant' => fn () => Demo\Outside\Caller::limit(),
    'a class outside writes through an internal set' => fn () => Demo\Outside\Caller::views(),
    'a class outside creates an internal class' => fn () => Demo\Outside\Caller::till(),
    'a class outside calls a method an internal enum declares' => fn () => Demo\Outside\Caller::label(),
    'a class outside calls from and tryFrom of an internal enum' => fn () => Demo\Outside\Caller::from(),
    'a function outside calls an internal method' => fn () => Demo\Outside\secret(),
    'top-level code of the namespace calls an internal method' => fn () => require __DIR__ . '/Demo/Store/boot.php',
    'a closure outside any class in the namespace calls an internal method' => fn () => (require __DIR__ . '/Demo/Store/closure.php')(),
    'a class of the namespace reaches every one' => fn () => Demo\Store\Shelf::all(),
    'a function of the namespace reaches every one' => fn () => Demo\Store\shelve(),
];
foreach ($calls as $label => $call) {
    try {
        ob_start();
        $call();
        ob_end_clean();
        echo json_encode([$label, null, null]), "\n";
    } catch (Error $error) {
        ob_end_clean();
        echo json_encode([$label, substr($error->getFile(), strlen(__DIR__) + 1) . ':' . $error->getLine(), $error->getMessage()]), "\n";
    }
}
PHP;
file_put_contents("$root/run.php", $runner);
$ran = shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n ' . escapeshellarg("$root/run.php") . ' 2>&1');

foreach (explode("\n", trim($ran)) as $line) {
    [$label, $at, $message] = json_decode($line, true) ?? [$line, null, null];
    if ($message === null) {
        echo "$label: runs\n";
        continue;
    }
    $found = $refused[$at] ?? [];
    unset($refused[$at]);
    echo "$label: $message\n";
    echo '  mago: ', $found === [$message] ? 'the same refusal' : json_encode($found), "\n";
}
echo 'refusals mago reports that the engine runs: ', json_encode($refused), "\n";

remove_project($root);
?>
--EXPECT--
mago analyze exits 1
a class outside calls an internal method: Call to internal method Demo\Store\Catalog::secret() from Demo\Outside\Caller, outside namespace Demo\Store
  mago: the same refusal
a class outside reads an internal constant: Cannot access internal constant Demo\Store\Catalog::LIMIT from Demo\Outside\Caller, outside namespace Demo\Store
  mago: the same refusal
a class outside writes through an internal set: Cannot modify internal(set) property Demo\Store\Catalog::$views from Demo\Outside\Caller, outside namespace Demo\Store
  mago: the same refusal
a class outside creates an internal class: Cannot instantiate internal class Demo\Store\Till from Demo\Outside\Caller, outside namespace Demo\Store
  mago: the same refusal
a class outside calls a method an internal enum declares: Call to internal method Demo\Store\Status::label() from Demo\Outside\Caller, outside namespace Demo\Store
  mago: the same refusal
a class outside calls from and tryFrom of an internal enum: runs
a function outside calls an internal method: Call to internal method Demo\Store\Catalog::secret() from Demo\Outside\secret, outside namespace Demo\Store
  mago: the same refusal
top-level code of the namespace calls an internal method: Call to internal method Demo\Store\Catalog::secret() from global scope, outside namespace Demo\Store
  mago: the same refusal
a closure outside any class in the namespace calls an internal method: Call to internal method Demo\Store\Catalog::secret() from global scope, outside namespace Demo\Store
  mago: the same refusal
a class of the namespace reaches every one: runs
a function of the namespace reaches every one: runs
refusals mago reports that the engine runs: []
