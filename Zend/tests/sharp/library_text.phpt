--TEST--
A PHP# class calls every method of Sharp.Text.Text, and Text.fromByte keeps chr's deprecation for a value that is not a byte
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Text/Text.sharp';
require __DIR__ . '/TextCalls.sharp';

var_dump(Demo\TextCalls::slug('Hello, World'));
var_dump(Demo\TextCalls::fromByte(65));
var_dump(Demo\TextCalls::fromByte(0) === "\0", Demo\TextCalls::fromByte(255) === "\xFF");
var_dump(Demo\TextCalls::fromByte(321));
?>
--EXPECTF--
string(11) "hello-world"
string(1) "A"
bool(true)
bool(true)

Deprecated: chr(): Providing a value not in-between 0 and 255 is deprecated, this is because a byte value must be in the [0, 255] interval. The value used will be constrained using % 256 in %sText.sharp on line 7
string(1) "A"
