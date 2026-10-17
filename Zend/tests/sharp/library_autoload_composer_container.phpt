--TEST--
A post-autoload-dump callback, which Composer runs in its own process, uses a PHP# class that Composer's own loader loads through the PHP file mago compile writes beside it, and builds it through Sharp\Container, which reads the library's module autoload.php names there too. On an engine the installed library does not match, each gets requireNative's refusal, without opcache and when opcache preloads the same code
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
    'app/Greeting.sharp' => "namespace App;\n\npublic class Greeting\n{\n    public Greeting(private string text) { }\n\n    public string now() => this.text;\n}\n",
    'app/Module.sharp' => "namespace App;\n\nmodule\n{\n    singleton Greeting(Environment environment) = new Greeting(environment.variable(\"SHARP_GREETING\") ?? \"no greeting\");\n}\n",
    'app/Clock.sharp' => "namespace App;\n\npublic class Clock\n{\n    public string now() => \"noon\";\n}\n",
    // vendor/autoload.php as Composer writes it, in short: Composer's own loader, which registers first and finds a
    // class's PHP file by the PSR-4 rule, then the files of the packages' "files" autoload.
    'vendor/autoload.php' => <<<'PHP'
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
            return false;
        }

        public static function getPrettyVersion(string $packageName): string
        {
            throw new \OutOfBoundsException("Package \"$packageName\" is not installed");
        }
    }
}

namespace {
    (new Composer\Autoload\ClassLoader([
        'App\\' => [dirname(__DIR__) . '/app'],
        'Sharp\\' => [__DIR__ . '/heyjordanparker/php-sharp-composer/library/Sharp'],
    ]))->register(__DIR__);
    require __DIR__ . '/heyjordanparker/php-sharp-composer/autoload.php';
}
PHP,
    'plugin_manager.php' => <<<'PHP'
<?php

namespace Composer\Plugin;

final class PluginManager
{
}
PHP,
    // A post-autoload-dump callback, as `App\ComposerScripts::run`: Composer has loaded its plugin manager, and the
    // project's autoloader, in its own process. The preload script runs the same code.
    'composer_script.php' => composer_script('callback'),
    'preload.php' => composer_script('preload'),
];
foreach (LIBRARY_MODULE as $library) {
    $files["vendor/heyjordanparker/php-sharp-composer/library/Sharp/$library.sharp"] = file_get_contents("$package/library/Sharp/$library.sharp");
}
$root = compile_project('library-autoload-composer-container', $files);

$preloading = ' -d opcache.enable=1 -d opcache.enable_cli=1 -d ' . escapeshellarg("opcache.preload=$root/preload.php");
foreach ([
    "the engine's own native bodies" => file_get_contents("$package/native.php"),
    'other native bodies' => '<?php return ' . var_export(['fingerprint' => '0123456789abcdef0123456789abcdef', 'bodies' => ['Sharp.Text.Text.slug']], true) . ';',
] as $case => $native) {
    file_put_contents("$root/vendor/heyjordanparker/php-sharp-composer/native.php", $native);
    foreach (['without opcache' => '', 'under opcache preloading' => $preloading] as $mode => $settings) {
        echo "== $case, $mode\n";
        echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . " -n$settings " . escapeshellarg("$root/composer_script.php") . ' 2>&1');
    }
}

remove_project($root);

function composer_script(string $who): string
{
    return str_replace('{who}', $who, <<<'PHP'
<?php

require __DIR__ . '/plugin_manager.php';
require __DIR__ . '/vendor/autoload.php';
foreach ([
    'App\Greeting' => static fn (): string => (new App\Greeting('hello'))->now(),
    'Sharp\Container' => static fn (): string => (new Sharp\Container())->get(App\Greeting::class)->now(),
    'a require of app/Clock.sharp' => static function (): string {
        require __DIR__ . '/app/Clock.sharp';

        return (new App\Clock())->now();
    },
] as $through => $run) {
    try {
        $result = $run();
    } catch (Throwable $error) {
        $result = get_class($error) . ': ' . str_replace(phpversion('sharp'), '<engine version>', $error->getMessage());
    }
    echo "{who} through $through: $result\n";
}
echo '{who}, app/Greeting.php included: ', in_array(__DIR__ . '/app/Greeting.php', get_included_files(), true) ? 'yes' : 'no', "\n";
PHP);
}
?>
--EXPECT--
== the engine's own native bodies, without opcache
callback through App\Greeting: hello
callback through Sharp\Container: no greeting
callback through a require of app/Clock.sharp: noon
callback, app/Greeting.php included: yes
== the engine's own native bodies, under opcache preloading
preload through App\Greeting: hello
preload through Sharp\Container: no greeting
preload through a require of app/Clock.sharp: noon
preload, app/Greeting.php included: yes
callback through App\Greeting: hello
callback through Sharp\Container: no greeting
callback through a require of app/Clock.sharp: noon
callback, app/Greeting.php included: no
== other native bodies, without opcache
callback through App\Greeting: Error: The PHP# standard library unknown was built for other native bodies than PHP# engine <engine version> has. Install the same PHP# version of both.
callback through Sharp\Container: Error: The PHP# standard library unknown was built for other native bodies than PHP# engine <engine version> has. Install the same PHP# version of both.
callback through a require of app/Clock.sharp: Error: The PHP# standard library unknown was built for other native bodies than PHP# engine <engine version> has. Install the same PHP# version of both.
callback, app/Greeting.php included: yes
== other native bodies, under opcache preloading
preload through App\Greeting: Error: The PHP# standard library unknown was built for other native bodies than PHP# engine <engine version> has. Install the same PHP# version of both.
preload through Sharp\Container: Error: The PHP# standard library unknown was built for other native bodies than PHP# engine <engine version> has. Install the same PHP# version of both.
preload through a require of app/Clock.sharp: Error: The PHP# standard library unknown was built for other native bodies than PHP# engine <engine version> has. Install the same PHP# version of both.
preload, app/Greeting.php included: yes
callback through App\Greeting: Error: The PHP# standard library unknown was built for other native bodies than PHP# engine <engine version> has. Install the same PHP# version of both.
callback through Sharp\Container: Error: The PHP# standard library unknown was built for other native bodies than PHP# engine <engine version> has. Install the same PHP# version of both.
callback through a require of app/Clock.sharp: Error: The PHP# standard library unknown was built for other native bodies than PHP# engine <engine version> has. Install the same PHP# version of both.
callback, app/Greeting.php included: yes
