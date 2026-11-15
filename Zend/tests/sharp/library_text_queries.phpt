--TEST--
A PHP# class calls the text members of Kotlin, Rust and .NET that PHP# lacked: compareTo, equals, indexOf, lastIndexOf, contains, startsWith and endsWith ignoring case, titlecase, replace of the first n, a code point's character, a list of bytes as text, the character classes and Unicode normalization, with each edge case. normalized runs on intl, which every engine builds, so sharp/bin/test --installed runs it on each runtime image
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Int.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/ListMembers.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/Text/StringMembers.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/Text/NormalizationForm.sharp';
require __DIR__ . '/TextQueries.sharp';

use Demo\TextQueries;

function refused(Closure $call): void
{
    try {
        $call();
    } catch (ValueError $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}

echo json_encode(TextQueries::compared('apple', 'Apple')), ' ', json_encode(TextQueries::compared('Zebra', 'apple')), "\n";
echo json_encode(TextQueries::equal('ÉCOLE', 'école')), ' ', json_encode(TextQueries::equal('Straße', 'STRASSE')), "\n";
echo json_encode(TextQueries::found('Hello hello', 'HELLO')), ' ', json_encode(TextQueries::found('ÉtÉ été', 'été')), "\n";
echo json_encode(TextQueries::held('Hello World', 'hello')), ' ', json_encode(TextQueries::held('Hello World', 'WORLD')), "\n";
echo TextQueries::titled("hello wORLD o'neil"), "\n";
echo TextQueries::replaced('banana', 'a', 'o', 1), ' ', TextQueries::replaced('banana', 'a', 'o', 0), ' ';
echo TextQueries::replaced('banana', 'a', 'o', 5), ' ', TextQueries::replaced('banana', 'a', 'o', null), ' ', TextQueries::replacedAll('banana', 'an', '_'), "\n";
refused(fn () => TextQueries::replaced('banana', 'a', 'o', -1));
echo TextQueries::replaced('ab', '', '-', 1), ' ', TextQueries::replaced('ab', '', '-', 2), ' ', TextQueries::replaced('ab', '', '-', 9), "\n";
echo TextQueries::character(65), ' ', TextQueries::character(0x1F600), "\n";
foreach ([0xD800, -1, 0x110000] as $codePoint) {
    refused(fn () => TextQueries::character($codePoint));
}
var_dump(TextQueries::bytes([104, 105]), TextQueries::bytes([]));
refused(fn () => TextQueries::bytes([104, 256]));
foreach (['abc', 'ABC', '123', " \t\n", '', 'é1', 'Ωmega'] as $text) {
    echo json_encode($text, JSON_UNESCAPED_UNICODE), ' ', json_encode(TextQueries::classes($text)), "\n";
}
refused(fn () => TextQueries::classes("\xff"));
echo bin2hex(TextQueries::composed("e\u{301}")), ' ', bin2hex(TextQueries::decomposed('é')), ' ';
echo TextQueries::compatible('ﬁ'), ' ', bin2hex(TextQueries::compatibleDecomposed('ﬁé')), "\n";
refused(fn () => TextQueries::composed("\xff"));
?>
--EXPECT--
[1,0] [-1,1]
[false,true] [false,false]
[null,0,null,6] [4,0,4,4]
[false,true,false,true,false,false] [false,true,false,false,false,true]
Hello World O'neil
bonana banana bonono bonono b__a
ValueError: `replace` takes a limit of 0 or more, and it is negative.
-ab -a-b -a-b-
A 😀
ValueError: `toCharacter` needs a Unicode scalar value, from 0 to 0x10FFFF and no surrogate, and the value is not one.
ValueError: `toCharacter` needs a Unicode scalar value, from 0 to 0x10FFFF and no surrogate, and the value is not one.
ValueError: `toCharacter` needs a Unicode scalar value, from 0 to 0x10FFFF and no surrogate, and the value is not one.
string(2) "hi"
string(0) ""
ValueError: `toByteString` needs a byte from 0 to 255, and the value is outside it.
"abc" [true,false,true,false,false,true]
"ABC" [true,false,true,false,true,false]
"123" [false,true,true,false,false,false]
" \t\n" [false,false,false,true,false,false]
"" [false,false,false,false,false,false]
"é1" [false,false,true,false,false,false]
"Ωmega" [true,false,true,false,false,false]
ValueError: `isLetter` reads UTF-8 text, and the text is not.
c3a9 65cc81 fi 666965cc81
ValueError: `normalized` reads UTF-8 text, and the text is not.
