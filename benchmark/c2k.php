<?php

declare(strict_types=1);

/*
 * Writes a benchmark project into a folder, with the Composer vendor folder of this checkout's `sharp/composer` package:
 * - c2k: 133 modules of 16 PHP# files each, 2,128 files with 1,330 laws, and one extension host whose return-type
 *   provider every `App\` method call asks.
 * - c2ks: c2k whose host also registers a codebase-scan hook.
 * - laws: the law corpus, each file of benchmark/laws under app/, with no host: one file per law shape a project hit.
 *
 * Usage: php benchmark/c2k.php c2k|c2ks|laws <folder>
 *
 * The folder must not exist. Composer installs the package from this checkout and its two dependencies from Packagist,
 * at the versions composer.json pins.
 */

const MODULES = 133;

[$script, $kind, $folder] = $argv + [null, null, null];
if (!in_array($kind, ['c2k', 'c2ks', 'laws'], true) || $folder === null) {
    fwrite(STDERR, "Usage: php {$script} c2k|c2ks|laws <folder>\n");
    exit(2);
}
if (file_exists($folder)) {
    fwrite(STDERR, "{$folder} exists.\n");
    exit(1);
}

$package = realpath(__DIR__ . '/../sharp/composer');
$hosts = $kind === 'laws' ? '' : "\n[extension-hosts.amounts]\nfile = \"tools/host.php\"\n";
write($folder, 'mago.toml', "php-version = \"8.4\"\n\n[source]\npaths = [\"app\"]\nincludes = [\"vendor\"]\n{$hosts}");
write($folder, 'composer.json', json_encode([
    'type' => 'library',
    'repositories' => [['type' => 'path', 'url' => $package, 'options' => ['symlink' => false]]],
    'require' => [
        'heyjordanparker/php-sharp-composer' => '*@dev',
        'psr/container' => '2.0.2',
        'psr/event-dispatcher' => '1.0.0',
    ],
    'autoload' => ['psr-4' => ['App\\' => 'app/']],
    'config' => ['allow-plugins' => ['heyjordanparker/php-sharp-composer' => true]],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
if ($kind === 'laws') {
    $corpus = __DIR__ . '/laws';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($corpus, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        write($folder, 'app/' . substr($file->getPathname(), strlen($corpus) + 1), file_get_contents($file->getPathname()));
    }
} else {
    write($folder, 'tools/host.php', host($kind === 'c2ks'));
    for ($module = 0; $module < MODULES; $module++) {
        foreach (module($module) as $name => $source) {
            write($folder, "app/M{$module}/{$name}.sharp", $source);
        }
    }
}

$install = proc_open(
    ['composer', 'install', '--no-interaction', '--no-progress', '--ignore-platform-reqs'],
    [1 => STDOUT, 2 => STDERR],
    $pipes,
    $folder,
);
exit(proc_close($install));

function write(string $folder, string $name, string $contents): void
{
    $path = "{$folder}/{$name}";
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0o777, true)) {
        throw new RuntimeException("Could not create the folder of {$path}.");
    }
    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException("Could not write {$path}.");
    }
}

/**
 * The extension host: a return-type provider every `App\` method call asks, which answers none, and in c2ks a
 * codebase-scan hook that selects the first module's files and reads nothing from them.
 */
function host(bool $scans): string
{
    $amounts = <<<'PHP'
        final class Amounts implements Plugin, MethodReturnTypeProvider
        {
            public function getDefinition(): PluginDefinition
            {
                return new PluginDefinition('amounts', 'Amounts', 'Asked for the type of every amount method call.');
            }

            public function register(PluginRegistry $registry): void
            {
                $registry->registerMethodReturnTypeProvider($this);
            }

            public function getTargets(): array
            {
                return [MethodTarget::allMethods('App\\*')];
            }

            public function getReturnType(ReturnTypeProviderContext $context): ?Type
            {
                return null;
            }
        }

        PHP;
    $scanner = <<<'PHP'
        final class Scans implements Plugin, CodebaseScanHook
        {
            public function getDefinition(): PluginDefinition
            {
                return new PluginDefinition('scans', 'Scans', 'Selects the first module for the codebase scan.');
            }

            public function register(PluginRegistry $registry): void
            {
                $registry->registerCodebaseScanHook($this);
            }

            public function getTargets(): array
            {
                return ['app/M0/*.sharp'];
            }

            public function scan(CodebaseScanContext $context): void
            {
            }
        }

        PHP;
    $uses = [
        'Mago\Sdk\Analyzer\MethodReturnTypeProvider',
        'Mago\Sdk\Analyzer\MethodTarget',
        'Mago\Sdk\Analyzer\Plugin',
        'Mago\Sdk\Analyzer\PluginDefinition',
        'Mago\Sdk\Analyzer\PluginRegistry',
        'Mago\Sdk\Analyzer\ReturnTypeProviderContext',
        'Mago\Sdk\Analyzer\Type',
        'Mago\Sdk\Extension',
        'Mago\Sdk\Worker',
    ];
    if ($scans) {
        array_unshift($uses, 'Mago\Sdk\Analyzer\CodebaseScanContext', 'Mago\Sdk\Analyzer\CodebaseScanHook');
    }
    $imports = implode('', array_map(static fn (string $use): string => "use {$use};\n", $uses));
    $plugins = $scans ? 'new Amounts(), new Scans()' : 'new Amounts()';
    $classes = $amounts . ($scans ? "\n" . $scanner : '');

    return "<?php\n\ndeclare(strict_types=1);\n\n{$imports}\n{$classes}\n"
        . "(new Worker(new Extension('amounts', 'Amounts', '0.0.0', analyzerPlugins: [{$plugins}])))->run();\n";
}

/**
 * The 16 files of module `$n`, by class name, with 10 laws that each end with the fixed steps: five on `Line` over the
 * priced chain, one on `Meter`, two `Math.max` laws on `Rates`, one on `Label` and one on the recursive `Steps`. The other
 * eleven classes hold no law and read them with `?:`, `match` and recursion; `Notices` reads the previous module's
 * `Notices`.
 *
 * @return array<string, string>
 */
function module(int $n): array
{
    $namespace = "namespace App.M{$n};\n\n";
    $before = 'App.M' . ($n - 1);
    $notices = $n === 0
        ? "public final class Notices\n{\n    public Notices(public Notices? previous { get; }, public Priced price { get; }) { }\n\n"
            . "    public static Notices of(Priced price) => new Notices(null, price);\n}\n"
        : "import {$before}.Notices as Earlier;\nimport {$before}.Priced as EarlierPriced;\n\n"
            . "public final class Notices\n{\n    public Notices(public Earlier? previous { get; }, public Priced price { get; }) { }\n\n"
            . "    public static Notices of(Priced price) => new Notices(new Earlier(null, new EarlierPriced(price.cents)), price);\n}\n";

    return array_map(static fn (string $body): string => $namespace . $body, [
        'Priced' => "public class Priced\n{\n    public Priced(public int cents { get; }) { }\n\n    public int doubled() => this.cents * 2;\n}\n",
        'Item' => "public class Item : Priced\n{\n    public Item(int cents) : super(cents) { }\n}\n",
        'Charge' => "public sealed interface Charge\n{\n}\n",
        'Fee' => "public final class Fee : Item, Charge\n{\n    public Fee(int cents) : super(cents) { }\n}\n",
        'Line' => "public class Line : Item\n{\n    public Line(public int id { get; }, int cents) : super(cents) { }\n\n"
            . "    law readsTop(Line line) => line.cents == line.cents;\n"
            . "    law builds(int id, int cents) => new Line(id, cents).cents == cents;\n"
            . "    law buildsTwice(int id, int cents) => new Line(id, cents).cents * 2 == cents * 2;\n"
            . "    law feeReadsTop(int cents) => new Fee(cents).cents == cents;\n"
            . "    law feeBuildsTwice(int cents) => new Fee(cents).cents * 2 == cents * 2;\n}\n",
        'Meter' => "public final class Meter\n{\n    public Meter(public int raw { get; }) { }\n\n"
            . "    public int doubled => this.raw * 2;\n\n"
            . "    public int capped\n    {\n        get {\n            if (this.raw > 10) {\n                return 10;\n            }\n            return this.raw;\n        }\n    }\n\n"
            . "    public int twice => this.helper();\n\n    public int helper() => this.raw + this.raw;\n\n"
            . "    law keepsRaw(int raw) => new Meter(raw).raw == raw;\n}\n",
        'Rates' => "public final class Rates\n{\n    public static int net(int paid, int refunded) => Math.max(paid - refunded, 0);\n\n"
            . "    law netIsNeverNegative(int paid, int refunded) => Rates.net(paid, refunded) >= 0;\n"
            . "    law maxIsAtLeast(int a, int b) => Math.max(a, b) >= a;\n}\n",
        'Notices' => $notices,
        'Tier' => "public enum Tier\n{\n    case Low;\n    case High;\n\n    public int weight() => match (this) {\n        Tier.Low => 1,\n        Tier.High => 2,\n    };\n}\n",
        'Window' => "public final class Window\n{\n    public static int sign(int n) => n > 5 ? 2 : (n < 0 ? 0 : 1);\n}\n",
        'Steps' => "public final class Steps\n{\n    public static int sumTo(int n) => n <= 0 ? 0 : n + Steps.sumTo(n - 1);\n\n"
            . "    law sumOfNothingIsZero() => Steps.sumTo(0) == 0;\n}\n",
        'Totals' => "public final class Totals\n{\n    public static int total(List<Priced> items) => match (items) {\n        [] => 0,\n"
            . "        [Priced first, ...List<Priced> rest] => first.cents + Totals.total(rest),\n    };\n}\n",
        'Bounds' => "public final class Bounds\n{\n    public static int clamp(int value) => Window.sign(value) == 0 ? 0 : Math.max(value, 1);\n}\n",
        'Score' => "public final class Score\n{\n    public static int of(Line line, Tier tier) => line.doubled() * tier.weight();\n}\n",
        'Rank' => "public final class Rank\n{\n    public static int of(Meter meter) => Rates.net(meter.capped, Steps.sumTo(2));\n}\n",
        'Label' => "public final class Label\n{\n    public Label(public string text { get; }) { }\n\n    public Label same() => new Label(this.text);\n\n"
            . "    law sameKeepsText(Label l) => l.same().text == l.text;\n}\n",
    ]);
}
