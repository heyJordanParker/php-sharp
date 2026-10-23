--TEST--
A post-autoload-dump callback, which Composer runs in its own process, uses PHP# classes through the PHP file mago compile writes beside one, the plugin's loader, Sharp\Container, a require and a require_once. Against the engine's own native bodies each runs. Compiled against a library built for other native bodies, each gets the refusal that names both versions: without opcache, when opcache preloads the same code, and with --no-plugins, where only Composer's loader runs
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
require __DIR__ . '/project.inc';

$package = dirname(__DIR__, 3) . '/sharp/composer';
$files = [
    'mago.toml' => "[source]\npaths = [\"app\"]\nincludes = [\"vendor\"]\n",
    'vendor/heyjordanparker/php-sharp-composer/composer.json' => '{"name": "heyjordanparker/php-sharp-composer"}',
    'vendor/heyjordanparker/php-sharp-composer/autoload.php' => file_get_contents("$package/autoload.php"),
    'vendor/heyjordanparker/php-sharp-composer/native.php' => file_get_contents("$package/native.php"),
    'vendor/composer/installed.json' => '{"packages": [{"name": "heyjordanparker/php-sharp-composer", "version": "9.9.9"}]}',
    'app/Greeting.sharp' => "namespace App;\n\npublic class Greeting\n{\n    public Greeting(private string text) { }\n\n    public string now() => this.text;\n}\n",
    'app/Module.sharp' => "namespace App;\n\nimport Sharp.System.Environment;\n\nmodule\n{\n    singleton Greeting(Environment environment) = new Greeting(environment.variable(\"SHARP_GREETING\") ?? \"no greeting\");\n}\n",
    'app/Clock.sharp' => "namespace App;\n\npublic class Clock\n{\n    public string now() => \"noon\";\n}\n",
    'app/Tick.sharp' => "namespace App;\n\npublic enum Tick\n{\n    case Later;\n    case Now(string at);\n}\n",
    // Composer's own loader, in short, which registers first and finds a class's PHP file by the PSR-4 rule. With
    // --no-plugins, Composer's process runs it alone.
    'vendor/composer/autoload_real.php' => <<<'PHP'
<?php

namespace Composer\Autoload {
    final class ClassLoader
    {
        private static array $loaders = [];

        public function __construct(private array $prefixes)
        {
        }

        public static function getRegisteredLoaders(): array
        {
            return self::$loaders;
        }

        public function isClassMapAuthoritative(): bool
        {
            return false;
        }

        public function getPrefixesPsr4(): array
        {
            return $this->prefixes;
        }

        public function getFallbackDirsPsr4(): array
        {
            return [];
        }

        public function register(string $vendorDir): void
        {
            self::$loaders[$vendorDir] = $this;
            spl_autoload_register(function (string $class): void {
                foreach ($this->prefixes as $prefix => $dirs) {
                    foreach (str_starts_with($class, $prefix) ? $dirs : [] as $dir) {
                        if (is_file($file = $dir . '/' . strtr(substr($class, strlen($prefix)), '\\', '/') . '.php')) {
                            include $file;

                            return;
                        }
                    }
                }
            }, true, true);
        }
    }
}

namespace Composer {
    final class InstalledVersions
    {
        public static function isInstalled(string $packageName): bool
        {
            return $packageName === 'heyjordanparker/php-sharp-composer';
        }

        public static function getPrettyVersion(string $packageName): string
        {
            return '9.9.9';
        }
    }
}

namespace {
    (new Composer\Autoload\ClassLoader([
        'App\\' => [dirname(__DIR__, 2) . '/app'],
        'Sharp\\' => [dirname(__DIR__) . '/heyjordanparker/php-sharp-composer/library/Sharp'],
    ]))->register(dirname(__DIR__));
}
PHP,
    // vendor/autoload.php as Composer writes it: Composer's own loader, then the files of the packages' "files"
    // autoload.
    'vendor/autoload.php' => <<<'PHP'
<?php

require __DIR__ . '/composer/autoload_real.php';
require __DIR__ . '/heyjordanparker/php-sharp-composer/autoload.php';
PHP,
    'plugin_manager.php' => <<<'PHP'
<?php

namespace Composer\Plugin;

final class PluginManager
{
}
PHP,
    'rows.php' => <<<'PHP'
<?php

return [
    'App\Greeting, through app/Greeting.php' => static fn (): string => (new App\Greeting('hello'))->now(),
    "App\\Tick\\Now, through the plugin's loader" => static fn (): string => (new App\Tick\Now('noon'))->at,
    'Sharp\Container' => static fn (): string => (new Sharp\Container())->get(App\Greeting::class)->now(),
    'a require of app/Clock.sharp' => static function (): string {
        require __DIR__ . '/app/Clock.sharp';

        return (new App\Clock())->now();
    },
    'a require_once of app/Clock.sharp' => static function (): string {
        require_once __DIR__ . '/app/Clock.sharp';

        return (new App\Clock())->now();
    },
];
PHP,
    // A post-autoload-dump callback, as `App\ComposerScripts::run`: Composer has loaded its plugin manager, and the
    // project's autoloader, in its own process. The preload script runs the same code. With --no-plugins, Composer
    // loads no package's "files", so neither the library's autoload.php nor its loader runs.
    'composer_script.php' => composer_script('callback', 'autoload.php', null),
    'preload.php' => composer_script('preload', 'autoload.php', null),
    'no_plugins.php' => composer_script('callback', 'composer/autoload_real.php', ['App\Greeting, through app/Greeting.php', 'a require of app/Clock.sharp', 'a require_once of app/Clock.sharp']),
];
foreach (LIBRARY_MODULE as $library) {
    $files["vendor/heyjordanparker/php-sharp-composer/library/Sharp/$library.sharp"] = file_get_contents("$package/library/Sharp/$library.sharp");
}
$root = compile_project('library-autoload-composer-container', $files);

$preloading = ' -d opcache.enable=1 -d opcache.enable_cli=1 -d ' . escapeshellarg("opcache.preload=$root/preload.php");
foreach ([
    "the engine's own native bodies" => file_get_contents("$package/native.php"),
    'other native bodies' => '<?php return ' . var_export(['fingerprint' => '0123456789abcdef0123456789abcdef', 'bodies' => ['Sharp.Text.StringMembers.slugOf']], true) . ';',
] as $case => $native) {
    file_put_contents("$root/vendor/heyjordanparker/php-sharp-composer/native.php", $native);
    run_mago($root);
    foreach ([
        'without opcache' => ['', 'composer_script.php'],
        'under opcache preloading' => [$preloading, 'composer_script.php'],
        'with --no-plugins' => ['', 'no_plugins.php'],
    ] as $mode => [$settings, $script]) {
        echo "== $case, $mode\n";
        echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . " -n$settings " . escapeshellarg("$root/$script") . ' 2>&1');
    }
}

remove_project($root);

function composer_script(string $who, string $autoload, ?array $rows): string
{
    return strtr(<<<'PHP'
<?php

require __DIR__ . '/plugin_manager.php';
require __DIR__ . '/vendor/{autoload}';
$rows = require __DIR__ . '/rows.php';
foreach ({rows} ?? array_keys($rows) as $through) {
    try {
        $result = $rows[$through]();
    } catch (Throwable $error) {
        $result = get_class($error) . ': ' . str_replace(phpversion('sharp'), '<engine version>', $error->getMessage());
    }
    echo "{who} through $through: $result\n";
}
PHP, ['{who}' => $who, '{autoload}' => $autoload, '{rows}' => var_export($rows, true)]);
}
?>
--EXPECT--
== the engine's own native bodies, without opcache
callback through App\Greeting, through app/Greeting.php: hello
callback through App\Tick\Now, through the plugin's loader: noon
callback through Sharp\Container: no greeting
callback through a require of app/Clock.sharp: noon
callback through a require_once of app/Clock.sharp: noon
== the engine's own native bodies, under opcache preloading
preload through App\Greeting, through app/Greeting.php: hello
preload through App\Tick\Now, through the plugin's loader: noon
preload through Sharp\Container: no greeting
preload through a require of app/Clock.sharp: noon
preload through a require_once of app/Clock.sharp: noon
callback through App\Greeting, through app/Greeting.php: hello
callback through App\Tick\Now, through the plugin's loader: noon
callback through Sharp\Container: no greeting
callback through a require of app/Clock.sharp: noon
callback through a require_once of app/Clock.sharp: noon
== the engine's own native bodies, with --no-plugins
callback through App\Greeting, through app/Greeting.php: hello
callback through a require of app/Clock.sharp: noon
callback through a require_once of app/Clock.sharp: noon
== other native bodies, without opcache
callback through App\Greeting, through app/Greeting.php: CompileError: This project's PHP# library is 9.9.9, and the PHP# engine is <engine version>. Install PHP# 9.9.9, or require heyjordanparker/php-sharp-composer <engine version>.
callback through App\Tick\Now, through the plugin's loader: Error: This project's PHP# library is 9.9.9, and the PHP# engine is <engine version>. Install PHP# 9.9.9, or require heyjordanparker/php-sharp-composer <engine version>.
callback through Sharp\Container: CompileError: This project's PHP# library is 9.9.9, and the PHP# engine is <engine version>. Install PHP# 9.9.9, or require heyjordanparker/php-sharp-composer <engine version>.
callback through a require of app/Clock.sharp: CompileError: This project's PHP# library is 9.9.9, and the PHP# engine is <engine version>. Install PHP# 9.9.9, or require heyjordanparker/php-sharp-composer <engine version>.
callback through a require_once of app/Clock.sharp: CompileError: This project's PHP# library is 9.9.9, and the PHP# engine is <engine version>. Install PHP# 9.9.9, or require heyjordanparker/php-sharp-composer <engine version>.
== other native bodies, under opcache preloading
preload through App\Greeting, through app/Greeting.php: CompileError: This project's PHP# library is 9.9.9, and the PHP# engine is <engine version>. Install PHP# 9.9.9, or require heyjordanparker/php-sharp-composer <engine version>.
preload through App\Tick\Now, through the plugin's loader: Error: This project's PHP# library is 9.9.9, and the PHP# engine is <engine version>. Install PHP# 9.9.9, or require heyjordanparker/php-sharp-composer <engine version>.
preload through Sharp\Container: CompileError: This project's PHP# library is 9.9.9, and the PHP# engine is <engine version>. Install PHP# 9.9.9, or require heyjordanparker/php-sharp-composer <engine version>.
preload through a require of app/Clock.sharp: CompileError: This project's PHP# library is 9.9.9, and the PHP# engine is <engine version>. Install PHP# 9.9.9, or require heyjordanparker/php-sharp-composer <engine version>.
preload through a require_once of app/Clock.sharp: CompileError: This project's PHP# library is 9.9.9, and the PHP# engine is <engine version>. Install PHP# 9.9.9, or require heyjordanparker/php-sharp-composer <engine version>.
callback through App\Greeting, through app/Greeting.php: CompileError: This project's PHP# library is 9.9.9, and the PHP# engine is <engine version>. Install PHP# 9.9.9, or require heyjordanparker/php-sharp-composer <engine version>.
callback through App\Tick\Now, through the plugin's loader: Error: This project's PHP# library is 9.9.9, and the PHP# engine is <engine version>. Install PHP# 9.9.9, or require heyjordanparker/php-sharp-composer <engine version>.
callback through Sharp\Container: CompileError: This project's PHP# library is 9.9.9, and the PHP# engine is <engine version>. Install PHP# 9.9.9, or require heyjordanparker/php-sharp-composer <engine version>.
callback through a require of app/Clock.sharp: CompileError: This project's PHP# library is 9.9.9, and the PHP# engine is <engine version>. Install PHP# 9.9.9, or require heyjordanparker/php-sharp-composer <engine version>.
callback through a require_once of app/Clock.sharp: CompileError: This project's PHP# library is 9.9.9, and the PHP# engine is <engine version>. Install PHP# 9.9.9, or require heyjordanparker/php-sharp-composer <engine version>.
== other native bodies, with --no-plugins
callback through App\Greeting, through app/Greeting.php: CompileError: This project's PHP# library is 9.9.9, and the PHP# engine is <engine version>. Install PHP# 9.9.9, or require heyjordanparker/php-sharp-composer <engine version>.
callback through a require of app/Clock.sharp: CompileError: This project's PHP# library is 9.9.9, and the PHP# engine is <engine version>. Install PHP# 9.9.9, or require heyjordanparker/php-sharp-composer <engine version>.
callback through a require_once of app/Clock.sharp: CompileError: This project's PHP# library is 9.9.9, and the PHP# engine is <engine version>. Install PHP# 9.9.9, or require heyjordanparker/php-sharp-composer <engine version>.
