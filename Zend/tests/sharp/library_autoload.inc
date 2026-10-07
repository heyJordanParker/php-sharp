<?php
// php library_autoload.inc <engine|other> <psr4|classmap>: loads sharp/composer/autoload.php beside a native.php that
// holds the engine's SHARP_NATIVE or another one, with Sharp\ found by PSR-4 or by an authoritative class map.

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

    [, $fingerprint, $layout] = $argv;
    $root = dirname(__DIR__, 3);
    $engine = require "$root/sharp/composer/native.php";
    $package = sys_get_temp_dir() . "/sharp_library_autoload_{$fingerprint}_$layout";
    @mkdir("$package/vendor/composer", 0777, true);
    copy("$root/sharp/composer/autoload.php", "$package/autoload.php");
    file_put_contents("$package/native.php", "<?php return '" . ($fingerprint === 'engine' ? $engine : '0123456789abcdef0123456789abcdef') . "';");

    $library = "$root/sharp/composer/library/Sharp";
    if ($layout === 'classmap') {
        file_put_contents("$package/vendor/composer/autoload_sharp.php", '<?php return ' . var_export([
            'Demo\\Elsewhere' => '/nowhere/Elsewhere.sharp',
            'Sharp\\Text\\Text' => "$library/Text/Text.sharp",
        ], true) . ';');
        $loader = new ClassLoader([], true);
    } else {
        $loader = new ClassLoader(['Sharp\\' => [$library]], false);
    }
    ClassLoader::$loaders = ["$package/vendor" => $loader];

    require "$package/autoload.php";
    echo 'autoload.php loaded, ', class_exists('Sharp\Text\Text', false) ? 'with' : 'with no', " library class declared\n";
    echo 'class map handed to Composer: ', json_encode(array_keys($loader->classMap)), "\n";
    try {
        echo \Sharp\Text\Text::slug('A B'), "\n";
    } catch (Error $error) {
        echo get_class($error), ': ', str_replace($engine, '<engine>', $error->getMessage()), "\n";
    }

    unlink("$package/autoload.php");
    unlink("$package/native.php");
    @unlink("$package/vendor/composer/autoload_sharp.php");
    rmdir("$package/vendor/composer");
    rmdir("$package/vendor");
    rmdir($package);
}
