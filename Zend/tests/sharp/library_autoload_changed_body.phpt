--TEST--
A standard library whose every native body exists in the engine but whose SHARP_NATIVE differs refuses when autoload.php is required, naming the package's version without a leading v and the engine's, or only the engine's when Composer does not report the package
--FILE--
<?php
foreach (['0.1.0', 'v0.1.0', 'absent'] as $version) {
    echo "== package $version\n";
    echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n ' . escapeshellarg(__DIR__ . '/library_autoload.inc') . " changed psr4 $version " . escapeshellarg('Sharp\Text\Text') . ' 2>&1');
}
?>
--EXPECT--
== package 0.1.0
Error: This project's PHP# library is 0.1.0, and the PHP# engine is <engine version>. Install PHP# 0.1.0, or require heyjordanparker/php-sharp-composer <engine version>.
0 .sharp files included
== package v0.1.0
Error: This project's PHP# library is 0.1.0, and the PHP# engine is <engine version>. Install PHP# 0.1.0, or require heyjordanparker/php-sharp-composer <engine version>.
0 .sharp files included
== package absent
Error: This project's PHP# library was built for another PHP# engine than <engine version>. Require heyjordanparker/php-sharp-composer <engine version>, or install the PHP# engine the library was built for.
0 .sharp files included
