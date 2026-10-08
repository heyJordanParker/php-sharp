--TEST--
On plain PHP, the first .sharp class of any namespace says the project needs the PHP# engine before its file is included, and autoload.php itself loads quietly
--FILE--
<?php
foreach ([['psr4', 'Sharp\Text\Text'], ['psr4', 'App\Clock'], ['classmap', 'App\Clock']] as [$layout, $class]) {
    echo "== $layout, $class\n";
    echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -d ' . escapeshellarg('disable_functions=sharp\internal\requirenative')
        . ' ' . escapeshellarg(__DIR__ . '/library_autoload.inc') . " engine $layout 0.1.0 " . escapeshellarg($class) . ' 2>&1');
}
?>
--EXPECT--
== psr4, Sharp\Text\Text
autoload.php loaded, with no library class declared
class map handed to Composer: []
Error: This project needs the PHP# engine, and this is plain PHP. Install php-sharp, or run the ghcr.io/heyjordanparker/php-sharp image.
== psr4, App\Clock
autoload.php loaded, with no library class declared
class map handed to Composer: []
Error: This project needs the PHP# engine, and this is plain PHP. Install php-sharp, or run the ghcr.io/heyjordanparker/php-sharp image.
== classmap, App\Clock
autoload.php loaded, with no library class declared
class map handed to Composer: []
Error: This project needs the PHP# engine, and this is plain PHP. Install php-sharp, or run the ghcr.io/heyjordanparker/php-sharp image.
