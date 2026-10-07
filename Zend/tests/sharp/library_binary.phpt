--TEST--
A PHP# class calls every method of Sharp.Data.Binary, and Binary.unpack throws ValueError on data too short for its format, after unpack's warning
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Data/Binary.sharp';
require __DIR__ . '/BinaryCalls.sharp';

use Demo\BinaryCalls;

var_dump(bin2hex(BinaryCalls::pack('nvc*', 0x1234, 0x5678, 65, 66)));
var_dump(BinaryCalls::unpack('nfirst/vsecond', "\x12\x34\x78\x56"));
var_dump(BinaryCalls::unpack('N', "\x00\x00\x01\x00"));

try {
    BinaryCalls::unpack('N', "\x00");
} catch (ValueError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECTF--
string(12) "123478564142"
array(2) {
  ["first"]=>
  int(4660)
  ["second"]=>
  int(22136)
}
array(1) {
  [1]=>
  int(256)
}

Warning: unpack(): Type N: not enough input values, need 4 values but only 1 was provided in %sBinary.sharp on line %d
ValueError: Binary.unpack: the data does not fit the format
