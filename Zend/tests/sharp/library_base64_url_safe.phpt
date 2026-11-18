--TEST--
PHP# Base64 writes and reads the URL-safe alphabet of RFC 4648 with urlSafe: true, as .NET's Base64Url: - and _ for + and /, no padding written, and padding read or not, while the standard alphabet stays as it was
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\EncodingQueries;

function refused(Closure $call): void
{
    try {
        $call();
    } catch (ValueError $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}

$bytes = "\xfb\xff\xbf?a";
echo EncodingQueries::encode($bytes, false), ' ', EncodingQueries::encode($bytes, true), "\n";
echo EncodingQueries::encode('a', false), ' ', EncodingQueries::encode('a', true), ' [', EncodingQueries::encode('', true), "]\n";
var_dump(EncodingQueries::decode('-_-_P2E', true) === $bytes, EncodingQueries::decode('-_-_P2E=', true) === $bytes);
var_dump(EncodingQueries::decode('YQ', true), EncodingQueries::decode('YQ==', true), EncodingQueries::decode('+/+/P2E=', false) === $bytes);
refused(fn () => EncodingQueries::decode('+/+/P2E=', true));
refused(fn () => EncodingQueries::decode('YQ=', true));
refused(fn () => EncodingQueries::decode('a b', true));
refused(fn () => EncodingQueries::decode('-_-_P2E=', false));
?>
--EXPECT--
+/+/P2E= -_-_P2E
YQ== YQ []
bool(true)
bool(true)
string(1) "a"
string(1) "a"
bool(true)
ValueError: Base64.decode: the text is not valid URL-safe base64
ValueError: Base64.decode: the text is not valid URL-safe base64
ValueError: Base64.decode: the text is not valid URL-safe base64
ValueError: Base64.decode: the text is not valid base64
