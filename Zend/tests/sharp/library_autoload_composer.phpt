--TEST--
Composer itself requires autoload.php when it loads the package as a plugin, so the check waits for the first .sharp file there: composer install and composer update run on plain PHP and on an engine the installed library does not match, and a .sharp class Composer's process reaches is refused before its file is included
--FILE--
<?php
foreach ([
    'plain PHP' => ['engine', '-d ' . escapeshellarg('disable_functions=sharp\internal\requirenative')],
    'other native bodies' => ['changed', ''],
    'a missing native body' => ['missing', ''],
] as $case => [$native, $settings]) {
    echo "== $case\n";
    echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . " -n $settings " . escapeshellarg(__DIR__ . '/library_autoload.inc') . " $native psr4 0.1.0 " . escapeshellarg('App\Clock') . ' composer 2>&1');
}
?>
--EXPECT--
== plain PHP
booted, with no library class declared
class map handed to Composer: []
Error: This project needs the PHP# engine, and this is plain PHP. Install php-sharp, or run the ghcr.io/heyjordanparker/php-sharp image.
0 .sharp files included
== other native bodies
booted, with no library class declared
class map handed to Composer: []
Error: This project's PHP# library is 0.1.0, and the PHP# engine is <engine version>. Install PHP# 0.1.0, or require heyjordanparker/php-sharp-composer <engine version>.
0 .sharp files included
== a missing native body
booted, with no library class declared
class map handed to Composer: []
Error: This project's PHP# library is 0.1.0, and the PHP# engine is <engine version>. Install PHP# 0.1.0, or require heyjordanparker/php-sharp-composer <engine version>.
0 .sharp files included
