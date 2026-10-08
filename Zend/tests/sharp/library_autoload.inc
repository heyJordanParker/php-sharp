<?php
// php library_autoload.inc <engine|changed|missing> <psr4|classmap> <version|absent> <class|composer>: requires
// sharp/composer/autoload.php beside a native.php that is the engine's own, one with another SHARP_NATIVE and the same
// native bodies, or one that also declares Text.missing, with Sharp\ and a project's App\Clock found by PSR-4 or by an
// authoritative class map, and Composer reporting the package at that version or not installed, then uses
// Sharp\Text\Text or App\Clock. With composer, Composer itself requires autoload.php, as it does to load a plugin, and
// uses no class.

namespace Composer\Plugin {
    if ($argv[4] === 'composer') {
        final class PluginManager
        {
        }
    }
}

namespace Composer {
    final class InstalledVersions
    {
        public static ?string $version = null;

        public static function isInstalled(string $packageName): bool
        {
            return $packageName === 'heyjordanparker/php-sharp-composer' && self::$version !== null;
        }

        public static function getPrettyVersion(string $packageName): string
        {
            return self::isInstalled($packageName) ? self::$version : throw new \OutOfBoundsException("Package \"$packageName\" is not installed");
        }
    }
}

namespace Composer\Autoload {
    final class ClassLoader
    {
        public static array $loaders = [];
        public array $classMap = [];

        public function __construct(private array $prefixes, private bool $authoritative)
        {
        }

        public static function getRegisteredLoaders(): array
        {
            return self::$loaders;
        }

        public function addClassMap(array $classMap): void
        {
            $this->classMap += $classMap;
        }

        public function isClassMapAuthoritative(): bool
        {
            return $this->authoritative;
        }

        public function getPrefixesPsr4(): array
        {
            return $this->prefixes;
        }

        public function getFallbackDirsPsr4(): array
        {
            return [];
        }
    }
}

namespace {
    use Composer\Autoload\ClassLoader;

    require __DIR__ . '/project.inc';

    [, $native, $layout, $version, $class] = $argv;
    Composer\InstalledVersions::$version = $version === 'absent' ? null : $version;
    $root = dirname(__DIR__, 3);
    $package = sys_get_temp_dir() . "/sharp_library_autoload_{$native}_{$layout}_{$version}_" . strtr($class, '\\', '_');
    @mkdir("$package/vendor/composer", 0777, true);
    @mkdir("$package/src/App", 0777, true);
    copy("$root/sharp/composer/autoload.php", "$package/autoload.php");
    if ($native === 'engine') {
        copy("$root/sharp/composer/native.php", "$package/native.php");
    } else {
        $bodies = $native === 'missing' ? ['Sharp.Text.Text.missing', 'Sharp.Text.Text.slug'] : ['Sharp.Text.Text.slug'];
        file_put_contents("$package/native.php", '<?php return ' . var_export(['fingerprint' => '0123456789abcdef0123456789abcdef', 'bodies' => $bodies], true) . ';');
    }
    file_put_contents("$package/src/App/Clock.sharp", "namespace App;\n\npublic class Clock\n{\n    public string now() => \"noon\";\n}\n");
    file_put_contents("$package/mago.toml", "[source]\npaths = [\"src\"]\nextensions = [\"sharp\"]\n");
    run_mago($package);

    $library = "$root/sharp/composer/library/Sharp";
    if ($layout === 'classmap') {
        file_put_contents("$package/vendor/composer/autoload_sharp.php", '<?php return ' . var_export([
            'App\\Clock' => "$package/src/App/Clock.sharp",
            'Sharp\\Text\\Text' => "$library/Text/Text.sharp",
        ], true) . ';');
        $loader = new ClassLoader([], true);
    } else {
        $loader = new ClassLoader(['Sharp\\' => [$library], 'App\\' => ["$package/src/App"]], false);
    }
    ClassLoader::$loaders = ["$package/vendor" => $loader];

    try {
        require "$package/autoload.php";
        echo 'booted, with ', class_exists('Sharp\Text\Text', false) ? 'a' : 'no', " library class declared\n";
        echo 'class map handed to Composer: ', json_encode(array_keys($loader->classMap)), "\n";
        if ($class !== 'composer') {
            echo $class === 'Sharp\Text\Text' ? \Sharp\Text\Text::slug('A B') : (new $class())->now(), "\n";
        }
    } catch (Error $error) {
        echo get_class($error), ': ', str_replace(phpversion('sharp'), '<engine version>', $error->getMessage()), "\n";
        echo count(array_filter(get_included_files(), static fn (string $file): bool => str_contains($file, '.sharp'))), " .sharp files included\n";
    }

    remove_project($package);
}
