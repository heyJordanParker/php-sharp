--TEST--
A PHP# class calls every method of Sharp.Text.Text, Text.fromByte throws ValueError for a value that is not a byte, parseCsv reads one CSV line, where a quoted field keeps its comma and a blank line is one empty field, case changes and split work on characters, replace replaces every match and throws ValueError for empty text to replace, split keeps the empty text after a trailing delimiter, gives at most a positive limit's parts, the last holding the rest, and every part for a limit of 0, and throws ValueError for an empty delimiter or a negative limit, and join puts the separator between a List<string>'s parts
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
foreach ([['a:b:c', 2], ['a:b:c', 1], ['a:b:c', 0], ['a:b:c', 5], ['é:Ü:ß', 2]] as [$text, $limit]) {
    echo json_encode(Demo\TextCalls::splitAtMost($text, ':', $limit), JSON_UNESCAPED_UNICODE), "\n";
}
foreach ([[['a', 'b', 'c'], ', '], [['é', 'Ü'], '–'], [['solo'], ', '], [[], ', '], [['a', 'b'], '']] as [$parts, $separator]) {
    var_dump(Demo\TextCalls::join($parts, $separator));
}
foreach ([fn () => Demo\TextCalls::split('abc', ''), fn () => Demo\TextCalls::splitAtMost('a:b', ':', -1), fn () => Demo\TextCalls::replace('abc', '', '-')] as $refused) {
    try {
        $refused();
    } catch (ValueError $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
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
["a","b:c"]
["a:b:c"]
["a","b","c"]
["a","b","c"]
["é","Ü:ß"]
string(7) "a, b, c"
string(7) "é–Ü"
string(4) "solo"
string(0) ""
string(2) "ab"
ValueError: `split` needs a delimiter, and it is empty.
ValueError: `split` takes a limit of 0 or more, and it is negative.
ValueError: `replace` needs text to replace, and it is empty.
