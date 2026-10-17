--TEST--
A standard library whose native bodies differ from the engine's refuses when autoload.php is required, so the app never prints booted and no .sharp file loads, whether PSR-4 or the class map finds .sharp classes
--FILE--
<?php
foreach (['psr4', 'classmap'] as $layout) {
    echo "== $layout\n";
    echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n ' . escapeshellarg(__DIR__ . '/library_autoload.inc') . " changed $layout 0.1.0 " . escapeshellarg('App\Clock') . ' 2>&1');
}
?>
--EXPECT--
== psr4
Error: This project's PHP# library is 0.1.0, and the PHP# engine is <engine version>. Install PHP# 0.1.0, or require heyjordanparker/php-sharp-composer <engine version>.
0 .sharp files included
== classmap
class map the plugin writes: ["App\\Clock","App\\Tick","App\\Tick\\Later","App\\Tick\\Now","App\\Timed","App\\Timed\\Defaults","App\\Ticking","App\\Ticking\\Defaults","App\\Stamp","Sharp\\Text\\Text"]
Error: This project's PHP# library is 0.1.0, and the PHP# engine is <engine version>. Install PHP# 0.1.0, or require heyjordanparker/php-sharp-composer <engine version>.
0 .sharp files included
