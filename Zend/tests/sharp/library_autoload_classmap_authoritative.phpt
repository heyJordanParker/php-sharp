--TEST--
A deploy runs an authoritative class-map dump before mago compile, so no PHP file sits beside a .sharp file and Composer's class map holds no PHP# class: the plugin's optimized dump maps each PHP# class to its .sharp file, and autoload.php loads a class, an interface's Defaults trait and the case class of an enum whose cases carry data by name through that map, while a class missing from it stays not found
--FILE--
<?php
require __DIR__ . '/project.inc';

$package = dirname(__DIR__, 3) . '/sharp/composer';
$library = 'vendor/heyjordanparker/php-sharp-composer';
$files = [];
foreach (LIBRARY_MODULE as $member) {
    $files["$library/library/Sharp/$member.sharp"] = file_get_contents("$package/library/Sharp/$member.sharp");
}
$root = compile_project('library-autoload-classmap-authoritative', $files + [
    'mago.toml' => "[source]\npaths = [\"app\"]\nincludes = [\"vendor\"]\n",
    "$library/composer.json" => '{"name": "heyjordanparker/php-sharp-composer"}',
    "$library/autoload.php" => file_get_contents("$package/autoload.php"),
    "$library/native.php" => file_get_contents("$package/native.php"),
    "$library/src/Plugin.php" => file_get_contents("$package/src/Plugin.php"),
    // A dev version names no release, so mago compiles against it.
    'vendor/composer/installed.json' => '{"packages": [{"name": "heyjordanparker/php-sharp-composer", "version": "9.9.x-dev"}]}',
    'app/Order.sharp' => "namespace App;\n\npublic class Order\n{\n    public string number() => \"A-12\";\n}\n",
    'app/Priced.sharp' => "namespace App;\n\npublic interface Priced\n{\n    int price() => 5;\n}\n",
    'app/Payment.sharp' => "namespace App;\n\npublic enum Payment\n{\n    case Pending;\n    case Paid(int amount);\n}\n",
    'vendor/composer/autoload_psr4.php' => "<?php\n\nreturn ['App\\\\' => [dirname(__DIR__, 2) . '/app']];\n",
    // Composer's loader after `--classmap-authoritative`: it answers only from its class map, which a dump made before
    // mago compile fills with no PHP# class, and autoload.php reads no PSR-4 folder of it.
    'vendor/composer/autoload_real.php' => <<<'PHP'
<?php

namespace Composer\Autoload {
    final class ClassLoader
    {
        private static array $loaders = [];

        public static function getRegisteredLoaders(): array
        {
            return self::$loaders;
        }

        public function isClassMapAuthoritative(): bool
        {
            return true;
        }

        public function getPrefixesPsr4(): array
        {
            return ['App\\' => [dirname(__DIR__, 2) . '/app']];
        }

        public function getFallbackDirsPsr4(): array
        {
            return [];
        }

        public function register(string $vendorDir): void
        {
            self::$loaders[$vendorDir] = $this;
            spl_autoload_register(static function (string $class): void {
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
            return '9.9.x-dev';
        }
    }
}

namespace {
    (new Composer\Autoload\ClassLoader())->register(dirname(__DIR__));
}
PHP,
    'vendor/autoload.php' => <<<'PHP'
<?php

require __DIR__ . '/composer/autoload_real.php';
require __DIR__ . '/heyjordanparker/php-sharp-composer/autoload.php';
PHP,
    // Composer's classes as dumpClassMap reaches them on `composer dump-autoload --optimize`.
    'dump.php' => <<<'PHP'
<?php

namespace Composer\Plugin {
    interface PluginInterface {}
}

namespace Composer\EventDispatcher {
    interface EventSubscriberInterface {}
}

namespace Composer\Util {
    final class Filesystem
    {
        public function filePutContentsIfModified(string $path, string $content): void
        {
            file_put_contents($path, $content);
        }

        public function findShortestPathCode(string $from, string $to, bool $directories, bool $staticCode): string
        {
            return var_export($to, true);
        }
    }
}

namespace Composer\Script {
    final class Event
    {
        public function __call(string $method, array $arguments): mixed
        {
            return match ($method) {
                'get' => __DIR__ . '/vendor',
                'findPackage' => null,
                'buildPackageMap', 'getCanonicalPackages' => [],
                'parseAutoloads' => ['exclude-from-classmap' => []],
                'getFlags' => ['optimize' => true],
                default => $this,
            };
        }
    }
}

namespace {
    require __DIR__ . '/vendor/heyjordanparker/php-sharp-composer/src/Plugin.php';
    (new PhpSharp\Composer\Plugin())->dumpClassMap(new Composer\Script\Event());
}
PHP,
    // Each name loads first in its own process, so no earlier load reads its file.
    'load.php' => <<<'PHP'
<?php

require __DIR__ . '/vendor/autoload.php';
echo $argv[1], ': ', match ($argv[1]) {
    'App\Order' => (new App\Order())->number(),
    'App\Priced\Defaults' => var_export(trait_exists('App\Priced\Defaults'), true),
    'App\Payment\Paid' => (new App\Payment\Paid(7))->amount,
    'App\Missing' => var_export(class_exists('App\Missing'), true),
}, "\n";
PHP,
]);

// The deploy's dump ran before mago compile, so no PHP file sits beside a .sharp file.
foreach (glob("$root/app/*.php") as $companion) {
    unlink($companion);
}
var_dump(glob("$root/app/*.php"));

$php = escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n ';
echo shell_exec($php . escapeshellarg("$root/dump.php") . ' 2>&1');
echo str_replace("$root/", '', file_get_contents("$root/vendor/composer/autoload_sharp.php")), "\n";

foreach (['App\Order', 'App\Priced\Defaults', 'App\Payment\Paid', 'App\Missing'] as $class) {
    echo shell_exec($php . escapeshellarg("$root/load.php") . ' ' . escapeshellarg($class) . ' 2>&1');
}

remove_project($root);
?>
--EXPECT--
array(0) {
}
<?php

return [
    'App\\Order' => 'app/Order.sharp',
    'App\\Payment' => 'app/Payment.sharp',
    'App\\Payment\\Paid' => 'app/Payment.sharp',
    'App\\Payment\\Pending' => 'app/Payment.sharp',
    'App\\Priced' => 'app/Priced.sharp',
    'App\\Priced\\Defaults' => 'app/Priced.sharp',
];

App\Order: A-12
App\Priced\Defaults: true
App\Payment\Paid: 7
App\Missing: false
