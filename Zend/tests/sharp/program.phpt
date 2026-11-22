--TEST--
A .sharp file whose class declares main runs as a program: PHP runs it by its path or through its #! line, the engine loads the project's Composer autoloader, builds the class through the container and exits with what main returns, while the autoloader and include only declare the class
--FILE--
<?php
require __DIR__ . '/program.inc';

$root = program_project('program', 'vendor');

run_program($root, 'app/Console/Import.sharp data.csv');
run_program($root, 'app/Console/Import.sharp');

// The #! line runs the php the PATH holds.
mkdir("$root/bin");
symlink(getenv('TEST_PHP_EXECUTABLE'), "$root/bin/php");
chmod("$root/app/Console/Import.sharp", 0755);
exec('cd ' . escapeshellarg($root) . ' && PATH=' . escapeshellarg("$root/bin:" . getenv('PATH')) . ' ./app/Console/Import.sharp data.csv 2>&1', $output, $status);
echo "./app/Console/Import.sharp data.csv\n", implode("\n", $output), $output === [] ? '' : "\n", "exit $status\n";

// Loaded by the autoloader or by include, the file declares its class and runs nothing.
run_program($root, '-r ' . escapeshellarg('require "vendor/autoload.php"; var_dump(class_exists("App\Console\Import")); echo "still running\n";'));
run_program($root, '-r ' . escapeshellarg('require "vendor/autoload.php"; include "app/Console/Import.sharp"; var_dump(class_exists("App\Console\Import", false)); echo "still running\n";'));

// A project whose .sharp folder records no autoloader refuses the program.
unlink("$root/.sharp/autoload.php");
exec('cd ' . escapeshellarg($root) . ' && ' . escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' ' . getenv('TEST_PHP_EXTRA_ARGS')
    . ' -d display_errors=stdout -d log_errors=0 app/Console/Import.sharp data.csv 2>&1', $refused, $status);
echo implode("\n", preg_grep('/Fatal error/', $refused)), "\nexit $status\n";

remove_project($root);
?>
--EXPECTF--
php app/Console/Import.sharp data.csv
exit 0
php app/Console/Import.sharp
exit 64
./app/Console/Import.sharp data.csv
exit 0
php -r 'require "vendor/autoload.php"; var_dump(class_exists("App\Console\Import")); echo "still running\n";'
  bool(true)
  still running
exit 0
php -r 'require "vendor/autoload.php"; include "app/Console/Import.sharp"; var_dump(class_exists("App\Console\Import", false)); echo "still running\n";'
  bool(true)
  still running
exit 0
Fatal error: app/Console/Import.sharp runs as a program, and .sharp/autoload.php, which loads the project's Composer autoloader, is missing. Run vendor/bin/mago compile. in %s/app/Console/Import.sharp on line %d
exit 255
