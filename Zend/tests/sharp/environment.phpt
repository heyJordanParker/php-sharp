--TEST--
PHP# Environment reads the process's variables, command-line arguments and current directory
--ENV--
SHARP_REGION=eu-west-1
--ARGS--
production --force
--FILE--
<?php

require __DIR__ . '/Deploy.sharp';

$deploy = Demo\Deploy::create();
var_dump($deploy->variable('SHARP_REGION'), $deploy->variable('SHARP_UNSET_REGION'));
var_dump($deploy->arguments() === $argv);
echo implode(' ', array_slice($deploy->arguments(), 1)), ' ', $deploy->target(), "\n";

$start = $deploy->folder();
var_dump($start === getcwd());
chdir(__DIR__ . '/harness');
var_dump($deploy->folder() === __DIR__ . '/harness');
chdir($start);
var_dump($deploy->folder() === $start);

$environment = new Sharp\Environment();
try {
    $environment->arguments = [];
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
try {
    $environment->currentDirectory = '/';
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
var_dump(isset($environment->arguments), empty($environment->currentDirectory), property_exists($environment, 'arguments'));
var_dump((new ReflectionClass(Sharp\Environment::class))->isFinal());
?>
--EXPECT--
string(9) "eu-west-1"
NULL
bool(true)
production --force production
bool(true)
bool(true)
bool(true)
Property Sharp\Environment::$arguments is read-only
Property Sharp\Environment::$currentDirectory is read-only
Indirect modification of Sharp\Environment::$arguments is not allowed
Cannot unset hooked property Sharp\Environment::$currentDirectory
bool(true)
bool(false)
bool(true)
bool(true)
