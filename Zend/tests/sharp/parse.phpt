--TEST--
Sharp\Int and Sharp\Float parse a string holding only a number, and tryParse gives null where parse throws
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Int.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/Float.sharp';

function attempt(callable $parse, mixed $value): void
{
    try {
        var_dump($parse($value));
    } catch (ValueError|ArithmeticError $e) {
        echo get_class($e), ': ', $e->getMessage(), "\n";
    }
}

foreach (['12', ' -12 ', "+007\t", '-9223372036854775808', '9223372036854775807'] as $value) {
    attempt(Sharp\Int::parse(...), $value);
}
foreach (['12abc', 'abc', '', ' ', '1.0', '1e3', '0x1A', '1 2', '+', '- 1', "\u{0661}", null, 12, 1.5, true, ['12'], new stdClass()] as $value) {
    attempt(Sharp\Int::parse(...), $value);
    attempt(Sharp\Int::tryParse(...), $value);
}
foreach (['9223372036854775808', '-9223372036854775809', '99999999999999999999'] as $value) {
    attempt(Sharp\Int::parse(...), $value);
    attempt(Sharp\Int::tryParse(...), $value);
}

foreach (['1.5', ' -0.25 ', '.5', '+3', '1e3', '2.5E-2', '-0', '00012.50'] as $value) {
    attempt(Sharp\Float::parse(...), $value);
}
foreach (['1,5', '1,000', '5.', '.', 'e3', '1e', '1e+', 'NaN', 'INF', '-Infinity', '0x1A', '1.5abc', '', null, 1.5, ['1.5'], new stdClass()] as $value) {
    attempt(Sharp\Float::parse(...), $value);
    attempt(Sharp\Float::tryParse(...), $value);
}
foreach (['1e999', '-1e999'] as $value) {
    attempt(Sharp\Float::parse(...), $value);
    attempt(Sharp\Float::tryParse(...), $value);
}
var_dump(Sharp\Float::parse('1e-999'));
?>
--EXPECT--
int(12)
int(-12)
int(7)
int(-9223372036854775808)
int(9223372036854775807)
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
ValueError: Int.parse: null is not a string
NULL
ValueError: Int.parse: int is not a string
NULL
ValueError: Int.parse: float is not a string
NULL
ValueError: Int.parse: bool is not a string
NULL
ValueError: Int.parse: array is not a string
NULL
ValueError: Int.parse: stdClass is not a string
NULL
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
ValueError: Float.parse: null is not a string
NULL
ValueError: Float.parse: float is not a string
NULL
ValueError: Float.parse: array is not a string
NULL
ValueError: Float.parse: stdClass is not a string
NULL
ArithmeticError: Float.parse: "1e999" is out of range for a float
NULL
ArithmeticError: Float.parse: "-1e999" is out of range for a float
NULL
float(0)
