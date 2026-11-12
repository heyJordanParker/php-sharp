--TEST--
The Composer plugin writes a global function for each [Export] method at every dump, and autoload.php's require of the file declares it before a library that declares its own only when none exists: the function passes positional and named arguments, which cross into the method's types, and returns its result
--FILE--
<?php
$package = sys_get_temp_dir() . '/sharp-test-exports-' . getmypid();
@mkdir("$package/src", 0777, true);
@mkdir("$package/vendor/composer", 0777, true);
copy(__DIR__ . '/../../../sharp/composer/src/Plugin.php', "$package/src/Plugin.php");
copy(__DIR__ . '/../../../sharp/composer/native.php', "$package/native.php");
file_put_contents("$package/vendor/composer/autoload_psr4.php", '<?php return ' . var_export(['Exports\\' => [__DIR__ . '/Exports']], true) . ';');
// Composer's classes as dumpClassMap reaches them on a dump without an optimized class map.
file_put_contents("$package/dump.php", <<<'PHP'
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
        }
    }

    namespace Composer\Script {
        final class Event
        {
            public function __construct(private string $vendor) {}

            public function __call(string $method, array $arguments): mixed
            {
                return match ($method) {
                    'get' => $this->vendor,
                    'findPackage' => null,
                    'buildPackageMap', 'getCanonicalPackages' => [],
                    'parseAutoloads' => ['exclude-from-classmap' => []],
                    'getFlags' => ['optimize' => false],
                    default => $this,
                };
            }
        }
    }

    namespace {
        require __DIR__ . '/src/Plugin.php';
        (new PhpSharp\Composer\Plugin())->dumpClassMap(new Composer\Script\Event(__DIR__ . '/vendor'));
    }
    PHP);
echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n ' . escapeshellarg("$package/dump.php") . ' 2>&1');

$exports = "$package/vendor/composer/autoload_sharp_exports.php";
echo file_get_contents($exports), "\n";

require __DIR__ . '/harness/fixture.inc';
fixture('Exports');
require_once $exports;

// A library that declares the function only when none exists, as WordPress's pluggable functions do, skips its own.
if (!function_exists('sharp_test_auth_redirect')) {
    function sharp_test_auth_redirect(string $to): string { return "library $to"; }
}

var_dump(sharp_test_auth_redirect('/home'));
var_dump(sharp_test_auth_redirect(status: 301, to: '/moved'));
var_dump(sharp_test_auth_kept(['a', '', 'b']));
var_dump(function_exists('plain'));
try {
    sharp_test_auth_redirect(5);
} catch (TypeError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}

foreach (['vendor/composer/autoload_sharp_exports.php', 'vendor/composer/autoload_sharp.php', 'vendor/composer/autoload_psr4.php', 'vendor/composer', 'vendor', 'native.php', 'dump.php', 'src/Plugin.php', 'src', ''] as $file) {
    is_dir("$package/$file") ? rmdir("$package/$file") : unlink("$package/$file");
}
?>
--EXPECTF--
<?php

declare(strict_types=1);

function sharp_test_auth_kept(mixed ...$arguments): mixed
{
    return \Exports\Auth::kept(...$arguments);
}

function sharp_test_auth_redirect(mixed ...$arguments): mixed
{
    return \Exports\Auth::redirect(...$arguments);
}

string(9) "302 /home"
string(10) "301 /moved"
array(2) {
  [0]=>
  string(1) "a"
  [1]=>
  string(1) "b"
}
bool(false)
TypeError: Exports\Auth::redirect(): Argument #1 ($to) must be of type string, int given%s
