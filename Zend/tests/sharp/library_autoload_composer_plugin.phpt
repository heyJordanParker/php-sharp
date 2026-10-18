--TEST--
The Composer plugin checks the installed library's native fingerprint at each post-autoload-dump, before it returns for a dump with no class map, and prints the refusal as an error line so Composer goes on: composer update runs the new version's plugin, which names the version Composer installed, or only the engine's when Composer does not report the package
--FILE--
<?php
$package = sys_get_temp_dir() . '/sharp-test-composer-plugin-' . getmypid();
@mkdir("$package/src", 0777, true);
copy(__DIR__ . '/../../../sharp/composer/src/Plugin.php', "$package/src/Plugin.php");
// Composer's classes as dumpClassMap reaches them: the event, the package the local repository holds, and the output.
file_put_contents("$package/dump.php", <<<'PHP'
    <?php
    namespace Composer\Plugin {
        interface PluginInterface {}
    }

    namespace Composer\EventDispatcher {
        interface EventSubscriberInterface {}
    }

    namespace Composer\Script {
        final class Event
        {
            public function __construct(private ?string $version) {}

            public function getComposer(): self
            {
                return $this;
            }

            public function getRepositoryManager(): self
            {
                return $this;
            }

            public function getLocalRepository(): self
            {
                return $this;
            }

            public function findPackage(string $name, string $constraint): ?self
            {
                return $name === 'heyjordanparker/php-sharp-composer' && $this->version !== null ? $this : null;
            }

            public function getPrettyVersion(): string
            {
                return $this->version;
            }

            public function getIO(): self
            {
                return $this;
            }

            public function writeError(string $line): void
            {
                echo '  ', str_replace(phpversion('sharp'), '<engine version>', $line), "\n";
            }

            public function getFlags(): array
            {
                return [];
            }
        }
    }

    namespace {
        require __DIR__ . '/src/Plugin.php';
        (new PhpSharp\Composer\Plugin())->dumpClassMap(new Composer\Script\Event($argv[1] === 'absent' ? null : $argv[1]));
        echo "  returned\n";
    }
    PHP);

$engine = file_get_contents(__DIR__ . '/../../../sharp/composer/native.php');
$other = '<?php return ' . var_export(['fingerprint' => '0123456789abcdef0123456789abcdef', 'bodies' => []], true) . ';';
foreach ([
    "the engine's native.php, 0.1.0" => [$engine, '0.1.0'],
    'other native bodies, v9.9.9' => [$other, 'v9.9.9'],
    'other native bodies, a package Composer does not report' => [$other, 'absent'],
] as $case => [$native, $version]) {
    file_put_contents("$package/native.php", $native);
    echo "$case:\n", shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n ' . escapeshellarg("$package/dump.php") . " $version 2>&1");
}

foreach (['native.php', 'dump.php', 'src/Plugin.php', 'src', ''] as $file) {
    is_dir("$package/$file") ? rmdir("$package/$file") : unlink("$package/$file");
}
?>
--EXPECT--
the engine's native.php, 0.1.0:
  returned
other native bodies, v9.9.9:
  <error>This project's PHP# library is 9.9.9, and the PHP# engine is <engine version>. Install PHP# 9.9.9, or require heyjordanparker/php-sharp-composer <engine version>.</error>
  returned
other native bodies, a package Composer does not report:
  <error>This project's PHP# library was built for another PHP# engine than <engine version>. Require heyjordanparker/php-sharp-composer <engine version>, or install the PHP# engine the library was built for.</error>
  returned
