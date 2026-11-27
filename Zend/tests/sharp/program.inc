<?php

require __DIR__ . '/project.inc';

// The program spec section 27 shows, which a project holds as app/Console/Import.sharp.
const IMPORT = <<<'SHARP'
#!/usr/bin/env php
namespace App.Console;

import Sharp.System.Environment;
import Sharp.System.ExitCode;

public class Import
{
    private Environment environment;

    public Import(Environment environment)
    {
        this.environment = environment;
    }

    public ExitCode main()
    {
        if (this.environment.arguments.count() < 2) {
            return new ExitCode(64);
        }

        return ExitCode.success;
    }
}

SHARP;

// An application whose Composer vendor folder is $vendor, composer.json's config.vendor-dir unless it is `vendor`, with
// the PHP# package installed in it, Composer's loader serving App\ from app/ and Sharp\ from the package's library, and
// Import.sharp, each file of $files written over the file of its path. Returns the project's folder, compiled.
function program_project(string $name, string $vendor, array $files = []): string
{
    $package = dirname(__DIR__, 3) . '/sharp/composer';
    $library = "$vendor/heyjordanparker/php-sharp-composer";
    $installed = [];
    foreach (LIBRARY_MODULE as $member) {
        $installed["$library/library/Sharp/$member.sharp"] = file_get_contents("$package/library/Sharp/$member.sharp");
    }
    $config = $vendor === 'vendor' ? '' : ', "config": {"vendor-dir": "' . $vendor . '"}';

    return compile_project($name, array_replace($installed + [
        'composer.json' => '{"type": "project", "autoload": {"psr-4": {"App\\\\": "app/"}}' . $config . "}\n",
        'mago.toml' => "[source]\npaths = [\"app\"]\nincludes = [\"$vendor\"]\n",
        "$library/composer.json" => '{"name": "heyjordanparker/php-sharp-composer"}',
        "$library/autoload.php" => file_get_contents("$package/autoload.php"),
        "$library/native.php" => file_get_contents("$package/native.php"),
        // A dev version names no release, so mago compiles against it.
        "$vendor/composer/installed.json" => '{"packages": [{"name": "heyjordanparker/php-sharp-composer", "version": "9.9.x-dev"}]}',
        "$vendor/composer/autoload_psr4.php" => <<<'PHP'
<?php

return [
    'App\\' => [dirname(__DIR__, 2) . '/app'],
    'Sharp\\' => [dirname(__DIR__) . '/heyjordanparker/php-sharp-composer/library/Sharp'],
];
PHP,
        // Composer's loader as the PHP# package's autoload.php reads it, and the version Composer reports the package at.
        "$vendor/composer/autoload_real.php" => <<<'PHP'
<?php

namespace Composer\Autoload {
    final class ClassLoader
    {
        private static array $loaders = [];

        private function __construct(private array $prefixes)
        {
        }

        public static function register(string $vendor): void
        {
            self::$loaders[$vendor] = new self(require __DIR__ . '/autoload_psr4.php');
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
    Composer\Autoload\ClassLoader::register(dirname(__DIR__));
}
PHP,
        "$vendor/autoload.php" => <<<'PHP'
<?php

require_once __DIR__ . '/composer/autoload_real.php';
require_once __DIR__ . '/heyjordanparker/php-sharp-composer/autoload.php';
PHP,
        'app/Console/Import.sharp' => IMPORT,
    ], $files));
}

// Runs $command in the project at $root with the PHP of the test, and prints what it wrote and its exit status.
function run_program(string $root, string $command): void
{
    $php = escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' ' . getenv('TEST_PHP_EXTRA_ARGS');
    exec('cd ' . escapeshellarg($root) . " && $php $command 2>&1", $output, $status);
    echo "php $command\n";
    foreach ($output as $line) {
        echo "  $line\n";
    }
    echo "exit $status\n";
}
