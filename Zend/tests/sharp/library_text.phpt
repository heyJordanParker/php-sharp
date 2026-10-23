--TEST--
A PHP# class calls every member of string in Sharp.Text and int.toByteString, toByteString throws ValueError for a value that is not a byte, parseCsv reads one CSV line, where a quoted field keeps its comma and a blank line is one empty field, case changes and split work on characters, replace replaces every match and throws ValueError for empty text to replace, split keeps the empty text after a trailing delimiter, gives at most a positive limit's parts, the last holding the rest, and every part for a limit of 0, and throws ValueError for an empty delimiter or a negative limit, and join puts the separator between a List<string>'s parts, length, substring, indexOf, lastIndexOf, padding, trimming whitespace at either end, removing a prefix or a suffix, capitalizing, repeat, lines and characters work on characters, a range or a start outside the text throws OutOfRangeException, and empty padding or a negative count throws ValueError
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
foreach ([fn () => Demo\TextCalls::split('abc', ''), fn () => Demo\TextCalls::splitAtMost('a:b', ':', -1), fn () => Demo\TextCalls::replace('abc', '', '-'), fn () => Demo\TextCalls::padStart('a', 3, ''), fn () => Demo\TextCalls::repeat('a', -1)] as $refused) {
    try {
        $refused();
    } catch (ValueError $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}

var_dump(Demo\TextCalls::length('école'), Demo\TextCalls::length(''));
var_dump(Demo\TextCalls::substring('école', 2), Demo\TextCalls::between('école', 1, 3), Demo\TextCalls::between('école', 5, 5));
var_dump(Demo\TextCalls::indexOf('ébène', 'è', 0), Demo\TextCalls::indexOf('banana', 'a', 2), Demo\TextCalls::indexOf('banana', 'x', 0), Demo\TextCalls::lastIndexOf('banana', 'a'), Demo\TextCalls::lastIndexOf('banana', 'x'));
foreach ([fn () => Demo\TextCalls::substring('abc', 4), fn () => Demo\TextCalls::between('abc', 2, 1), fn () => Demo\TextCalls::substring('abc', -1), fn () => Demo\TextCalls::indexOf('abc', 'a', 4)] as $refused) {
    try {
        $refused();
    } catch (OutOfRangeException $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}
var_dump(Demo\TextCalls::trimmed("\u{00A0} tea \n"), Demo\TextCalls::trimmedStart('  tea  '), Demo\TextCalls::trimmedEnd("  tea\u{2003}"));
var_dump(Demo\TextCalls::withoutPrefix('/path/', '/'), Demo\TextCalls::withoutSuffix('/path/', '/'), Demo\TextCalls::withoutSuffix('path', ''), Demo\TextCalls::withoutPrefix('path', 'x'));
var_dump(Demo\TextCalls::padStart('7', 3, '0'), Demo\TextCalls::padStart('é', 3, 'ü'), Demo\TextCalls::padEnd('ab', 4), Demo\TextCalls::padStart('long', 2, '0'));
var_dump(Demo\TextCalls::capitalized('école'), Demo\TextCalls::decapitalized('École'), Demo\TextCalls::repeat('ab', 3), Demo\TextCalls::repeat('ab', 0));
echo json_encode(Demo\TextCalls::lines("a\r\nb\nc\rd\n"), JSON_UNESCAPED_UNICODE), ' ', json_encode(Demo\TextCalls::lines('')), "\n";
echo json_encode(Demo\TextCalls::characters('éa'), JSON_UNESCAPED_UNICODE), ' ', json_encode(Demo\TextCalls::characters('')), "\n";
echo json_encode(Demo\TextCalls::codePoints('éa€')), ' ', json_encode(Demo\TextCalls::codePoints('')), "\n";
?>
--EXPECT--
string(11) "hello-world"
string(1) "A"
bool(true)
bool(true)
ValueError: `toByteString` needs a byte from 0 to 255, and the value is outside it.
ValueError: `toByteString` needs a byte from 0 to 255, and the value is outside it.
ValueError: `toByteString` needs a byte from 0 to 255, and the value is outside it.
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
ValueError: `padStart` needs text to pad with, and it is empty.
ValueError: `repeat` takes a count of 0 or more, and it is negative.
int(5)
int(0)
string(3) "ole"
string(2) "co"
string(0) ""
int(2)
int(3)
NULL
int(5)
NULL
OutOfRangeException: `substring` takes a range inside the text, and the range is outside it.
OutOfRangeException: `substring` takes a range inside the text, and the range is outside it.
OutOfRangeException: `substring` takes a range inside the text, and the range is outside it.
OutOfRangeException: `indexOf` starts inside the text, and the start is outside it.
string(3) "tea"
string(5) "tea  "
string(5) "  tea"
string(5) "path/"
string(5) "/path"
string(4) "path"
string(4) "path"
string(3) "007"
string(6) "üüé"
string(4) "ab  "
string(4) "long"
string(6) "École"
string(6) "école"
string(6) "ababab"
string(0) ""
["a","b","c","d",""] [""]
["é","a"] []
[233,97,8364] []
