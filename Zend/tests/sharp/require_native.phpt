--TEST--
Sharp\Internal\requireNative accepts the engine's own SHARP_NATIVE whatever the library's version, and refuses a standard library built for other native bodies, naming both PHP# versions
--FILE--
<?php
$engine = require __DIR__ . '/../../../sharp/composer/native.php';
var_dump(\Sharp\Internal\requireNative($engine, '0.1.0'));

try {
    \Sharp\Internal\requireNative('0123456789abcdef0123456789abcdef', '0.1.0');
} catch (Error $error) {
    echo get_class($error), ': ', str_replace(phpversion('sharp'), '<engine version>', $error->getMessage()), "\n";
}
?>
--EXPECT--
NULL
Error: The PHP# standard library 0.1.0 needs the native bodies of PHP# engine 0.1.0, and this engine is <engine version>. Install the same PHP# version of both.
