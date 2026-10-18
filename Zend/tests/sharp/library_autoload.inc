<?php
// php library_autoload.inc <engine|changed|missing> <psr4|classmap> <version|absent> <class> [composer]: requires
// sharp/composer/autoload.php beside the library's composer.json, its Module.sharp and the classes it binds, compiled, and a native.php that is the engine's own, one with another SHARP_NATIVE and the same
// native bodies, or one that also declares Text.missing, with Sharp\ and a project's App\Clock found by PSR-4 or by an
// authoritative class map, and Composer reporting the package at that version or not installed, then uses
// Sharp\Text\Text, App\Clock, App\Tick\Now, a case of the enum App/Tick.sharp declares, or the trait App\Timed\Defaults
// of the interface App/Timed.sharp declares, or App\Ticking\Defaults of an interface without a default body, through a
// plain PHP class, or App\Stamp, whose type line starts with an attribute. The class map lists the classes the package's
// Composer plugin finds in each file, which it prints. With composer, Composer itself requires autoload.php, as it does to load a plugin.
// With uncompiled, autoload.php is required before mago compiles the project, as on a fresh clone, and the project's
// module binds App\Greeting through the library's Environment, which Sharp\Container then builds.

namespace Composer\Plugin {
    if (($argv[5] ?? null) === 'composer') {
        final class PluginManager
        {
        }
    }

    interface PluginInterface
    {
    }
}

namespace Composer\EventDispatcher {
    interface EventSubscriberInterface
    {
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
    $package = sys_get_temp_dir() . "/sharp_library_autoload_{$native}_{$layout}_{$version}_" . strtr($class, '\\', '_') . '_' . ($argv[5] ?? 'app') . '_' . getmypid();
    @mkdir("$package/vendor/composer", 0777, true);
    @mkdir("$package/src/App", 0777, true);
    @mkdir("$package/library/Sharp/Time", 0777, true);
    @mkdir("$package/library/Sharp/Net", 0777, true);
    @mkdir("$package/library/Sharp/Text", 0777, true);
    copy("$root/sharp/composer/autoload.php", "$package/autoload.php");
    file_put_contents("$package/composer.json", '{"name": "heyjordanparker/php-sharp-composer"}');
    foreach (LIBRARY_MODULE as $library) {
        copy("$root/sharp/composer/library/Sharp/$library.sharp", "$package/library/Sharp/$library.sharp");
    }
    if ($native === 'engine') {
        copy("$root/sharp/composer/native.php", "$package/native.php");
    } else {
        $bodies = $native === 'missing' ? ['Sharp.Text.Text.missing', 'Sharp.Text.Text.slug'] : ['Sharp.Text.Text.slug'];
        file_put_contents("$package/native.php", '<?php return ' . var_export(['fingerprint' => '0123456789abcdef0123456789abcdef', 'bodies' => $bodies], true) . ';');
    }
    file_put_contents("$package/src/App/Clock.sharp", "namespace App;\n\npublic class Clock\n{\n    public string now() => \"noon\";\n}\n");
    file_put_contents("$package/src/App/Tick.sharp", "namespace App;\n\npublic enum Tick\n{\n    case Later;\n    case Now(string at);\n}\n");
    file_put_contents("$package/src/App/Timed.sharp", "namespace App;\n\npublic interface Timed\n{\n    string now() => \"default noon\";\n}\n");
    file_put_contents("$package/src/App/Ticking.sharp", "namespace App;\n\npublic interface Ticking\n{\n    string now();\n}\n");
    file_put_contents("$package/src/App/Stamp.sharp", "namespace App;\n\nimport Attribute;\n\n[Attribute] public class Stamp\n{\n    public string now() => \"stamped\";\n}\n");
    // An extern declaration file, which declares no type, named like the class its line declares. The class map reads
    // it, and mago compiles only src and library.
    @mkdir("$package/stubs/App", 0777, true);
    file_put_contents("$package/stubs/App/Failed.sharp", "namespace App;\n\nimport Lib.Failed;\n\nextern event Failed;\n");
    file_put_contents("$package/mago.toml", "[source]\npaths = [\"src\", \"library\"]\nextensions = [\"sharp\"]\n");
    $uncompiled = ($argv[5] ?? null) === 'uncompiled';
    if ($uncompiled) {
        file_put_contents("$package/src/App/Greeting.sharp", "namespace App;\n\npublic class Greeting\n{\n    public Greeting(private string text) { }\n\n    public string now() => this.text;\n}\n");
        file_put_contents("$package/src/App/Module.sharp", "namespace App;\n\nmodule\n{\n    singleton Greeting(Environment environment) = new Greeting(environment.variable(\"SHARP_GREETING\") ?? \"no greeting\");\n}\n");
    } else {
        run_mago($package);
    }

    $library = "$root/sharp/composer/library/Sharp";
    if ($layout === 'classmap') {
        require "$root/sharp/composer/src/Plugin.php";
        $declaredClasses = new ReflectionMethod(PhpSharp\Composer\Plugin::class, 'declaredClasses');
        $classMap = [];
        foreach (["$package/src/App/Clock.sharp", "$package/src/App/Tick.sharp", "$package/src/App/Timed.sharp", "$package/src/App/Ticking.sharp", "$package/src/App/Stamp.sharp", "$package/stubs/App/Failed.sharp", "$library/Text/Text.sharp", "$library/Module.sharp"] as $file) {
            $classMap += array_fill_keys($declaredClasses->invoke(null, new SplFileInfo($file)), $file);
        }
        // A module's file and an extern declaration file declare no type, spec section 27, so the class map lists
        // nothing for them.
        echo 'class map the plugin writes: ', json_encode(array_keys($classMap)), "\n";
        file_put_contents("$package/vendor/composer/autoload_sharp.php", '<?php return ' . var_export($classMap, true) . ';');
        $loader = new ClassLoader([], true);
    } else {
        $loader = new ClassLoader(['Sharp\\' => [$library], 'App\\' => ["$package/src/App"]], false);
    }
    ClassLoader::$loaders = ["$package/vendor" => $loader];

    try {
        require "$package/autoload.php";
        echo 'booted, with ', class_exists('Sharp\Text\Text', false) ? 'a' : 'no', " library class declared\n";
        echo 'class map handed to Composer: ', json_encode(array_keys($loader->classMap)), "\n";
        if ($uncompiled) {
            run_mago($package);
        }
        echo match ($class) {
            'Sharp\Text\Text' => \Sharp\Text\Text::slug('A B'),
            'App\Tick\Now' => (new $class('noon'))->at . (class_exists('App\Tick', false) ? ', with its enum' : ''),
            // A plain PHP class gets a PHP# interface's default bodies by using its trait, spec section 22.
            'App\Timed\Defaults' => (new class implements \App\Timed { use \App\Timed\Defaults; })->now(),
            // An interface without a default body has no Defaults trait, and asking for it after the interface loaded
            // leaves the interface's file unread.
            'App\Ticking\Defaults' => interface_exists(\App\Ticking::class)
                ? (new class implements \App\Ticking { use \App\Ticking\Defaults; })->now()
                : 'no interface',
            'App\Greeting' => (new Sharp\Container())->get($class)->now(),
            default => (new $class())->now(),
        }, "\n";
    } catch (Error $error) {
        echo get_class($error), ': ', str_replace(phpversion('sharp'), '<engine version>', $error->getMessage()), "\n";
        echo count(array_filter(get_included_files(), static fn (string $file): bool => str_contains($file, '.sharp'))), " .sharp files included\n";
    }

    remove_project($package);
}
