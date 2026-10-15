--TEST--
Sharp\Int and Sharp\Float parse a string holding only a number, with no spaces around it, tryParse gives null where parse throws, and both take only a string
--FILE--
<?php declare(strict_types=1);
require __DIR__ . '/../../../sharp/composer/library/Sharp/Int.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/Float.sharp';

function attempt(callable $parse, mixed $value): void
{
    try {
        var_dump($parse($value));
    } catch (ValueError|ArithmeticError|TypeError $e) {
        echo get_class($e), ': ', $e->getMessage(), "\n";
    }
}

foreach (['12', '-12', '+007', '-9223372036854775808', '9223372036854775807'] as $value) {
    attempt(Sharp\Int::parse(...), $value);
}
foreach ([' -12 ', "+007\t", '12abc', 'abc', '', ' ', '1.0', '1e3', '0x1A', '1 2', '+', '- 1', "\u{0661}", null, 12, 1.5, true, ['12'], new stdClass()] as $value) {
    attempt(Sharp\Int::parse(...), $value);
    attempt(Sharp\Int::tryParse(...), $value);
}
foreach (['9223372036854775808', '-9223372036854775809', '99999999999999999999'] as $value) {
    attempt(Sharp\Int::parse(...), $value);
    attempt(Sharp\Int::tryParse(...), $value);
}

foreach (['1.5', '-0.25', '.5', '+3', '1e3', '2.5E-2', '-0', '00012.50'] as $value) {
    attempt(Sharp\Float::parse(...), $value);
}
foreach ([' -0.25 ', '1,5', '1,000', '5.', '.', 'e3', '1e', '1e+', 'NaN', 'INF', '-Infinity', '0x1A', '1.5abc', '', null, 1.5, ['1.5'], new stdClass()] as $value) {
    attempt(Sharp\Float::parse(...), $value);
    attempt(Sharp\Float::tryParse(...), $value);
}
foreach (['1e999', '-1e999'] as $value) {
    attempt(Sharp\Float::parse(...), $value);
    attempt(Sharp\Float::tryParse(...), $value);
}
var_dump(Sharp\Float::parse('1e-999'));
?>
--EXPECTF--
int(12)
int(-12)
int(7)
int(-9223372036854775808)
int(9223372036854775807)
ValueError: Int.parse: " -12 " is not an int: remove its spaces with Text.trim() first
NULL
ValueError: Int.parse: "+007	" is not an int: remove its spaces with Text.trim() first
NULL
ValueError: Int.parse: "12abc" is not an int
NULL
ValueError: Int.parse: "abc" is not an int
NULL
ValueError: Int.parse: "" is not an int
NULL
ValueError: Int.parse: " " is not an int
NULL
ValueError: Int.parse: "1.0" is not an int
NULL
ValueError: Int.parse: "1e3" is not an int
NULL
ValueError: Int.parse: "0x1A" is not an int
NULL
ValueError: Int.parse: "1 2" is not an int
NULL
ValueError: Int.parse: "+" is not an int
NULL
ValueError: Int.parse: "- 1" is not an int
NULL
ValueError: Int.parse: "١" is not an int
NULL
TypeError: Sharp\Int::parse(): Argument #1 ($text) must be of type string, null given, called in %s on line %d
TypeError: Sharp\Int::tryParse(): Argument #1 ($text) must be of type string, null given, called in %s on line %d
TypeError: Sharp\Int::parse(): Argument #1 ($text) must be of type string, int given, called in %s on line %d
TypeError: Sharp\Int::tryParse(): Argument #1 ($text) must be of type string, int given, called in %s on line %d
TypeError: Sharp\Int::parse(): Argument #1 ($text) must be of type string, float given, called in %s on line %d
TypeError: Sharp\Int::tryParse(): Argument #1 ($text) must be of type string, float given, called in %s on line %d
TypeError: Sharp\Int::parse(): Argument #1 ($text) must be of type string, true given, called in %s on line %d
TypeError: Sharp\Int::tryParse(): Argument #1 ($text) must be of type string, true given, called in %s on line %d
TypeError: Sharp\Int::parse(): Argument #1 ($text) must be of type string, array given, called in %s on line %d
TypeError: Sharp\Int::tryParse(): Argument #1 ($text) must be of type string, array given, called in %s on line %d
TypeError: Sharp\Int::parse(): Argument #1 ($text) must be of type string, stdClass given, called in %s on line %d
TypeError: Sharp\Int::tryParse(): Argument #1 ($text) must be of type string, stdClass given, called in %s on line %d
ArithmeticError: Int.parse: "9223372036854775808" is out of range for an int
NULL
ArithmeticError: Int.parse: "-9223372036854775809" is out of range for an int
NULL
ArithmeticError: Int.parse: "99999999999999999999" is out of range for an int
NULL
float(1.5)
float(-0.25)
float(0.5)
float(3)
float(1000)
float(0.025)
float(-0)
float(12.5)
ValueError: Float.parse: " -0.25 " is not a float: remove its spaces with Text.trim() first
NULL
ValueError: Float.parse: "1,5" is not a float
NULL
ValueError: Float.parse: "1,000" is not a float
NULL
ValueError: Float.parse: "5." is not a float
NULL
ValueError: Float.parse: "." is not a float
NULL
ValueError: Float.parse: "e3" is not a float
NULL
ValueError: Float.parse: "1e" is not a float
NULL
ValueError: Float.parse: "1e+" is not a float
NULL
ValueError: Float.parse: "NaN" is not a float
NULL
ValueError: Float.parse: "INF" is not a float
NULL
ValueError: Float.parse: "-Infinity" is not a float
NULL
ValueError: Float.parse: "0x1A" is not a float
NULL
ValueError: Float.parse: "1.5abc" is not a float
NULL
ValueError: Float.parse: "" is not a float
NULL
TypeError: Sharp\Float::parse(): Argument #1 ($text) must be of type string, null given, called in %s on line %d
TypeError: Sharp\Float::tryParse(): Argument #1 ($text) must be of type string, null given, called in %s on line %d
TypeError: Sharp\Float::parse(): Argument #1 ($text) must be of type string, float given, called in %s on line %d
TypeError: Sharp\Float::tryParse(): Argument #1 ($text) must be of type string, float given, called in %s on line %d
TypeError: Sharp\Float::parse(): Argument #1 ($text) must be of type string, array given, called in %s on line %d
TypeError: Sharp\Float::tryParse(): Argument #1 ($text) must be of type string, array given, called in %s on line %d
TypeError: Sharp\Float::parse(): Argument #1 ($text) must be of type string, stdClass given, called in %s on line %d
TypeError: Sharp\Float::tryParse(): Argument #1 ($text) must be of type string, stdClass given, called in %s on line %d
ArithmeticError: Float.parse: "1e999" is out of range for a float
NULL
ArithmeticError: Float.parse: "-1e999" is out of range for a float
NULL
float(0)
