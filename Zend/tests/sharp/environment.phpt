--TEST--
PHP# Environment reads the process's variables, command-line arguments and current directory
--ENV--
SHARP_REGION=eu-west-1
--ARGS--
production --force
--FILE--
<?php

require __DIR__ . '/../../../sharp/composer/library/Sharp/Environment.sharp';
require __DIR__ . '/Deploy.sharp';

$deploy = Demo\Deploy::create();
var_dump($deploy->variable('SHARP_REGION'), $deploy->variable('SHARP_UNSET_REGION'));
var_dump($deploy->arguments() === $argv);
echo implode(' ', array_slice($deploy->arguments(), 1)), ' ', $deploy->target(), "\n";

$start = $deploy->folder();
var_dump($start === getcwd());
chdir(__DIR__ . '/harness');
var_dump($deploy->folder() === $start, Demo\Deploy::create()->folder() === __DIR__ . '/harness');
chdir($start);

var_dump(Sharp\Environment::require('SHARP_REGION'));
try {
    Sharp\Environment::require('SHARP_UNSET_REGION');
} catch (ValueError $e) {
    echo $e->getMessage(), "\n";
}

$code = 'require ' . var_export(__DIR__ . '/../../../sharp/composer/library/Sharp/Environment.sharp', true) . ';'
    . ' require ' . var_export(__DIR__ . '/Deploy.sharp', true) . ';'
    . ' try { Demo\Deploy::create()->target(); } catch (OutOfRangeException $e) { echo get_class($e), "\n"; }';
echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' ' . getenv('TEST_PHP_EXTRA_ARGS') . ' -r ' . escapeshellarg($code) . ' 2>&1');

$environment = new Sharp\Environment();
try {
    $environment->arguments = [];
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
try {
    $environment->arguments[] = 'extra';
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
try {
    unset($environment->currentDirectory);
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
string(9) "eu-west-1"
NULL
bool(true)
production --force production
bool(true)
bool(true)
bool(true)
string(9) "eu-west-1"
Environment.require: the environment variable "SHARP_UNSET_REGION" is not set
OutOfRangeException
Cannot modify readonly property Sharp\Environment::$arguments
Cannot indirectly modify readonly property Sharp\Environment::$arguments
Cannot unset readonly property Sharp\Environment::$currentDirectory
