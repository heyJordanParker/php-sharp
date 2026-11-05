--TEST--
On plain PHP, requiring autoload.php boots, so PHP tools such as mago run, and the first .sharp class says the project needs the PHP# engine before its file loads, as a test run's bootstrap does before the runner includes a .sharp test file itself
--FILE--
<?php
foreach ([['psr4', 'psr4', ''], ['classmap', 'classmap', ''], ['a test run', 'psr4', ' runner']] as [$case, $layout, $mode]) {
    echo "== $case\n";
    echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -d ' . escapeshellarg('disable_functions=sharp\internal\requirenative')
        . ' ' . escapeshellarg(__DIR__ . '/library_autoload.inc') . " engine $layout 0.1.0 " . escapeshellarg('App\Clock') . "$mode 2>&1");
}
?>
--EXPECT--
== psr4
booted, with no library class declared
class map handed to Composer: []
Error: This project needs the PHP# engine, and this is plain PHP. Install php-sharp, or run the ghcr.io/heyjordanparker/php-sharp image.
0 .sharp files included
== classmap
class map the plugin writes: ["App\\Clock","App\\Tick","App\\Tick\\Later","App\\Tick\\Now","App\\Timed","App\\Timed\\Defaults","App\\Ticking","App\\Ticking\\Defaults","App\\Stamp","Sharp\\Text\\StringMembers"]
booted, with no library class declared
class map handed to Composer: []
Error: This project needs the PHP# engine, and this is plain PHP. Install php-sharp, or run the ghcr.io/heyjordanparker/php-sharp image.
0 .sharp files included
== a test run
booted, with no library class declared
class map handed to Composer: []
Error: This project needs the PHP# engine, and this is plain PHP. Install php-sharp, or run the ghcr.io/heyjordanparker/php-sharp image.
0 .sharp files included
