--TEST--
mago compile records the Composer autoloader of the vendor folder composer.json's config.vendor-dir names in .sharp/autoload.php, which a program loads and .sharp/preload.php requires
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
require __DIR__ . '/program.inc';

$root = program_project('program-vendor-dir', 'lib');

run_program($root, 'app/Console/Import.sharp data.csv');
run_program($root, 'app/Console/Import.sharp');

echo file_get_contents("$root/.sharp/autoload.php");
echo implode('', array_filter(file("$root/.sharp/preload.php"), static fn (string $line): bool => str_contains($line, 'autoload'))), "\n";

// The preload loads the autoloader before the project's types, as a request does.
echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n ' . getenv('TEST_PHP_EXTRA_ARGS') . ' -d opcache.enable=1 -d opcache.enable_cli=1'
    . ' -d ' . escapeshellarg("opcache.preload=$root/.sharp/preload.php")
    . ' -r ' . escapeshellarg('var_dump(class_exists("App\Console\Import", false), class_exists("Composer\Autoload\ClassLoader", false));') . ' 2>&1');

remove_project($root);
?>
--EXPECT--
php app/Console/Import.sharp data.csv
exit 0
php app/Console/Import.sharp
exit 64
<?php

// mago compile writes this file, the project's Composer autoloader. It rewrites it on every compile.

require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/autoload.php';

bool(true)
bool(true)
