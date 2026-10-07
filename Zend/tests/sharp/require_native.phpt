--TEST--
Sharp\Internal\requireNative accepts the engine's own SHARP_NATIVE and refuses a standard library built for other native bodies
--FILE--
<?php
$engine = require __DIR__ . '/../../../sharp/composer/native.php';
var_dump(\Sharp\Internal\requireNative($engine));

try {
    \Sharp\Internal\requireNative('0123456789abcdef0123456789abcdef');
} catch (Error $error) {
    echo get_class($error), ': ', str_replace($engine, '<engine>', $error->getMessage()), "\n";
}
?>
--EXPECT--
NULL
Error: The PHP# standard library in vendor/ was built for other native bodies than this engine has (library 0123456789abcdef0123456789abcdef, engine <engine>). Install the heyjordanparker/php-sharp-composer version that matches this PHP# build.
