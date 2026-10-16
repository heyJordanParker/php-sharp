--TEST--
A PHP# class calls every method of Sharp.Text.Text, Text.fromByte throws ValueError for a value that is not a byte, and parseCsv reads one CSV line, where a quoted field keeps its comma and a blank line is one empty field
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Text/Text.sharp';
require __DIR__ . '/TextCalls.sharp';

var_dump(Demo\TextCalls::slug('Hello, World'));
var_dump(Demo\TextCalls::fromByte(65));
var_dump(Demo\TextCalls::fromByte(0) === "\0", Demo\TextCalls::fromByte(255) === "\xFF");

foreach ([321, 256, -1] as $byte) {
    try {
        Demo\TextCalls::fromByte($byte);
    } catch (ValueError $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}

foreach (['A1,"tea, hot",3', 'a,,b', '', 'a\\"b,c'] as $line) {
    echo json_encode(Demo\TextCalls::fields($line)), "\n";
}
?>
--EXPECT--
string(11) "hello-world"
string(1) "A"
bool(true)
bool(true)
ValueError: Text.fromByte: the value is not a byte from 0 to 255
ValueError: Text.fromByte: the value is not a byte from 0 to 255
ValueError: Text.fromByte: the value is not a byte from 0 to 255
["A1","tea, hot","3"]
["a","","b"]
[""]
["a\\\"b","c"]
