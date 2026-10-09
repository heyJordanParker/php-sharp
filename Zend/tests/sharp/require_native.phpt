--TEST--
Sharp\Internal\requireNative accepts the engine's own native.php whatever the library's version, refuses a library that declares a native body the engine lacks by naming the body, refuses one built for other native bodies by naming both PHP# versions, and refuses a body that is not named by a string
--FILE--
<?php
['fingerprint' => $fingerprint, 'bodies' => $bodies] = require __DIR__ . '/../../../sharp/composer/native.php';
var_dump(\Sharp\Internal\requireNative($fingerprint, $bodies, '0.1.0'));

foreach ([['Sharp.Text.Text.slug', 'Sharp.Json.Json.decode'], ['Sharp.Text.Text.slug'], [7]] as $declared) {
    try {
        \Sharp\Internal\requireNative('0123456789abcdef0123456789abcdef', $declared, '0.1.0');
    } catch (Error $error) {
        echo get_class($error), ': ', str_replace(phpversion('sharp'), '<engine version>', $error->getMessage()), "\n";
    }
}
?>
--EXPECT--
NULL
Error: The PHP# standard library 0.1.0 declares the native body Json.decode, which PHP# engine <engine version> does not have. Install the same PHP# version of both.
Error: The PHP# standard library 0.1.0 was built for other native bodies than PHP# engine <engine version> has. Install the same PHP# version of both.
TypeError: Sharp\Internal\requireNative(): Argument #2 ($bodies) must contain only strings, int given
