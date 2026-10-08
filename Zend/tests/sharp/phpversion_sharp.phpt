--TEST--
phpversion('sharp') gives the PHP_SHARP_VERSION that ext/sharp/php_sharp.h defines
--FILE--
<?php
preg_match('/^#define PHP_SHARP_VERSION "([0-9]+\.[0-9]+\.[0-9]+)"$/m', file_get_contents(__DIR__ . '/../../../ext/sharp/php_sharp.h'), $defined);
var_dump($defined !== [] && phpversion('sharp') === $defined[1]);
?>
--EXPECT--
bool(true)
