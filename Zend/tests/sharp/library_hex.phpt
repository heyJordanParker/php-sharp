--TEST--
A PHP# class calls every method of Sharp.Data.Hex, and Hex.decode throws ValueError on text that is not hexadecimal, after hex2bin's warning
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Data/Hex.sharp';
require __DIR__ . '/HexCalls.sharp';

use Demo\HexCalls;

var_dump(HexCalls::encode('abc'), HexCalls::decode('616263'));

try {
    HexCalls::decode('zz');
} catch (ValueError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECTF--
string(6) "616263"
string(3) "abc"

Warning: hex2bin(): Input string must be hexadecimal string in %sHex.sharp on line %d
ValueError: Hex.decode: the text is not hexadecimal with an even length
