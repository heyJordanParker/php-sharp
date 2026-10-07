--TEST--
A PHP# class calls every method of Sharp.Data.Compression, and Compression.uncompress throws ValueError on data that is not zlib-compressed, with no warning
--EXTENSIONS--
zlib
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Data/Compression.sharp';
require __DIR__ . '/CompressionCalls.sharp';

use Demo\CompressionCalls;

$compressed = CompressionCalls::compress('hello hello hello');
var_dump(bin2hex(substr($compressed, 0, 2)), CompressionCalls::uncompress($compressed));

try {
    CompressionCalls::uncompress('nope');
} catch (ValueError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
string(4) "789c"
string(17) "hello hello hello"
ValueError: Compression.uncompress: the data is not zlib-compressed
