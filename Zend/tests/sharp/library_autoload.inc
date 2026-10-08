<?php
// php library_autoload.inc <engine|other> <psr4|classmap> <version|absent> <class>: loads sharp/composer/autoload.php
// beside a native.php that holds the engine's SHARP_NATIVE or another one, with Sharp\ and a project's App\Clock found
// by PSR-4 or by an authoritative class map, and Composer reporting the package at that version or not installed, then
// uses Sharp\Text\Text or App\Clock.

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

    [, $fingerprint, $layout, $version, $class] = $argv;
    Composer\InstalledVersions::$version = $version === 'absent' ? null : $version;
    $root = dirname(__DIR__, 3);
    $engine = require "$root/sharp/composer/native.php";
    $package = sys_get_temp_dir() . "/sharp_library_autoload_{$fingerprint}_{$layout}_{$version}_" . strtr($class, '\\', '_');
    @mkdir("$package/vendor/composer", 0777, true);
    @mkdir("$package/src/App", 0777, true);
    copy("$root/sharp/composer/autoload.php", "$package/autoload.php");
    file_put_contents("$package/native.php", "<?php return '" . ($fingerprint === 'engine' ? $engine : '0123456789abcdef0123456789abcdef') . "';");
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

    require "$package/autoload.php";
    echo 'autoload.php loaded, ', class_exists('Sharp\Text\Text', false) ? 'with' : 'with no', " library class declared\n";
    echo 'class map handed to Composer: ', json_encode(array_keys($loader->classMap)), "\n";
    try {
        echo $class === 'Sharp\Text\Text' ? \Sharp\Text\Text::slug('A B') : (new $class())->now(), "\n";
    } catch (Error $error) {
        echo get_class($error), ': ', str_replace(phpversion('sharp'), '<engine version>', $error->getMessage()), "\n";
    }

    remove_project($package);
}
