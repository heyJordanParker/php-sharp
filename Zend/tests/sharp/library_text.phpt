--TEST--
A PHP# class calls every method of Sharp.Text.Text, Text.fromByte throws ValueError for a value that is not a byte, parseCsv reads one CSV line, where a quoted field keeps its comma and a blank line is one empty field, case changes and split work on characters, replace replaces every match, and split keeps the empty text after a trailing delimiter and throws ValueError for an empty delimiter
--FILE--
<?php
require __DIR__ . '/library_text.inc';

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

var_dump(Demo\TextCalls::contains('hello', 'ell'), Demo\TextCalls::contains('hello', 'xyz'));
var_dump(Demo\TextCalls::lowercase('ÉCOLE'), Demo\TextCalls::uppercase('école'), Demo\TextCalls::uppercase('straße'));
var_dump(Demo\TextCalls::startsWith('hello', 'he'), Demo\TextCalls::startsWith('hello', 'lo'));
var_dump(Demo\TextCalls::endsWith('hello', 'lo'), Demo\TextCalls::endsWith('hello', 'he'));
var_dump(Demo\TextCalls::replace('banana', 'a', 'o'), Demo\TextCalls::replace('banana', 'x', 'o'));

foreach ([['a,b,', ','], ['é–Ü', '–'], ['abc', ',']] as [$text, $delimiter]) {
    echo json_encode(Demo\TextCalls::split($text, $delimiter), JSON_UNESCAPED_UNICODE), "\n";
}
try {
    Demo\TextCalls::split('abc', '');
} catch (ValueError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
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
bool(true)
bool(false)
string(6) "école"
string(6) "ÉCOLE"
string(7) "STRASSE"
bool(true)
bool(false)
bool(true)
bool(false)
string(6) "bonono"
string(6) "banana"
["a","b",""]
["é","Ü"]
["abc"]
ValueError: Text.split: the delimiter is empty
