--TEST--
A PHP# class calls every method of Sharp.Encoding.Compression, and Compression.uncompress throws ValueError on data that is not zlib-compressed, with no warning
--EXTENSIONS--
zlib
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\CompressionCalls;

// Plain PHP passes a string where PHP# takes Bytes, and passes back the Bytes it was given.
$compressed = CompressionCalls::compress('hello hello hello');
var_dump(CompressionCalls::header($compressed) === [0x78, 0x9c], CompressionCalls::uncompress($compressed));

try {
    CompressionCalls::uncompress('nope');
} catch (ValueError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
bool(true)
string(17) "hello hello hello"
ValueError: Compression.uncompress: the data is not zlib-compressed
