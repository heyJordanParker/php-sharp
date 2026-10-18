--TEST--
Sharp\Internal\requireNative accepts the engine's own native.php whatever the library's version, and refuses a library built for other native bodies by naming both PHP# versions and the two fixes: the library's version without a leading v, the same version built from other sources, or no version Composer reports
--FILE--
<?php
['fingerprint' => $fingerprint, 'bodies' => $bodies] = require __DIR__ . '/../../../sharp/composer/native.php';
var_dump(\Sharp\Internal\requireNative($fingerprint, $bodies, '0.1.0'));

foreach (['0.1.0', 'v0.1.0', phpversion('sharp'), ''] as $version) {
    try {
        \Sharp\Internal\requireNative('0123456789abcdef0123456789abcdef', $bodies, $version);
    } catch (Error $error) {
        echo get_class($error), ': ', str_replace(phpversion('sharp'), '<engine version>', $error->getMessage()), "\n";
    }
}
?>
--EXPECT--
NULL
Error: This project's PHP# library is 0.1.0, and the PHP# engine is <engine version>. Install PHP# 0.1.0, or require heyjordanparker/php-sharp-composer <engine version>.
Error: This project's PHP# library is 0.1.0, and the PHP# engine is <engine version>. Install PHP# 0.1.0, or require heyjordanparker/php-sharp-composer <engine version>.
Error: This project's PHP# library and the PHP# engine are both <engine version>, but were built from different sources. Rebuild the PHP# engine, or reinstall heyjordanparker/php-sharp-composer.
Error: This project's PHP# library was built for another PHP# engine than <engine version>. Require heyjordanparker/php-sharp-composer <engine version>, or install the PHP# engine the library was built for.
