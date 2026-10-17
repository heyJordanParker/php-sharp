--TEST--
A post-autoload-dump callback, which Composer runs in its own process, builds a PHP# service through Sharp\Container: autoload.php names the library's module whenever the engine runs, inside Composer's process too, where Composer's own loader loads the class through the PHP file mago compile writes beside it
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
    // A post-autoload-dump callback, as `App\ComposerScripts::run`: Composer has loaded its plugin manager, and the
    // project's autoloader, in its own process.
    'composer_script.php' => <<<'PHP'
<?php

namespace Composer\Plugin {
    final class PluginManager
    {
    }
}

namespace {
    require __DIR__ . '/vendor/autoload.php';
    try {
        echo (new Sharp\Container())->get(App\Greeting::class)->now(), "\n";
    } catch (Throwable $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
    echo 'App\Greeting loaded through app/Greeting.php: ', in_array(__DIR__ . '/app/Greeting.php', get_included_files(), true) ? 'yes' : 'no', "\n";
}
PHP,
];
foreach (LIBRARY_MODULE as $library) {
    $files["vendor/heyjordanparker/php-sharp-composer/library/Sharp/$library.sharp"] = file_get_contents("$package/library/Sharp/$library.sharp");
}
$root = compile_project('library-autoload-composer-container', $files);

echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n ' . escapeshellarg("$root/composer_script.php") . ' 2>&1');

remove_project($root);
?>
--EXPECT--
no greeting
App\Greeting loaded through app/Greeting.php: yes
