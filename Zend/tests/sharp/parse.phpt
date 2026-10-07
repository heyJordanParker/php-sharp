--TEST--
Sharp\Int and Sharp\Float parse a string holding only a number, and tryParse gives null where parse throws
--FILE--
<?php

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
foreach (['12abc', 'abc', '', ' ', '1.0', '1e3', '0x1A', '1 2', '+', '- 1', "\u{0661}", null, 12, 1.5, true] as $value) {
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
foreach (['1,5', '1,000', '5.', '.', 'e3', '1e', '1e+', 'NaN', 'INF', '-Infinity', '0x1A', '1.5abc', '', null, 1.5] as $value) {
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
ValueError: Sharp\Int::parse(): Argument #1 ($value) must hold an int, '12abc' given
NULL
ValueError: Sharp\Int::parse(): Argument #1 ($value) must hold an int, 'abc' given
NULL
ValueError: Sharp\Int::parse(): Argument #1 ($value) must hold an int, '' given
NULL
ValueError: Sharp\Int::parse(): Argument #1 ($value) must hold an int, ' ' given
NULL
ValueError: Sharp\Int::parse(): Argument #1 ($value) must hold an int, '1.0' given
NULL
ValueError: Sharp\Int::parse(): Argument #1 ($value) must hold an int, '1e3' given
NULL
ValueError: Sharp\Int::parse(): Argument #1 ($value) must hold an int, '0x1A' given
NULL
ValueError: Sharp\Int::parse(): Argument #1 ($value) must hold an int, '1 2' given
NULL
ValueError: Sharp\Int::parse(): Argument #1 ($value) must hold an int, '+' given
NULL
ValueError: Sharp\Int::parse(): Argument #1 ($value) must hold an int, '- 1' given
NULL
ValueError: Sharp\Int::parse(): Argument #1 ($value) must hold an int, '\xD9\xA1' given
NULL
ValueError: Sharp\Int::parse(): Argument #1 ($value) must be a string, null given
NULL
ValueError: Sharp\Int::parse(): Argument #1 ($value) must be a string, int given
NULL
ValueError: Sharp\Int::parse(): Argument #1 ($value) must be a string, float given
NULL
ValueError: Sharp\Int::parse(): Argument #1 ($value) must be a string, true given
NULL
ArithmeticError: Sharp\Int::parse(): Argument #1 ($value) must hold an int from PHP_INT_MIN to PHP_INT_MAX, '922337203685477...' given
NULL
ArithmeticError: Sharp\Int::parse(): Argument #1 ($value) must hold an int from PHP_INT_MIN to PHP_INT_MAX, '-92233720368547...' given
NULL
ArithmeticError: Sharp\Int::parse(): Argument #1 ($value) must hold an int from PHP_INT_MIN to PHP_INT_MAX, '999999999999999...' given
NULL
float(1.5)
float(-0.25)
float(0.5)
float(3)
float(1000)
float(0.025)
float(-0)
float(12.5)
ValueError: Sharp\Float::parse(): Argument #1 ($value) must hold a float, '1,5' given
NULL
ValueError: Sharp\Float::parse(): Argument #1 ($value) must hold a float, '1,000' given
NULL
ValueError: Sharp\Float::parse(): Argument #1 ($value) must hold a float, '5.' given
NULL
ValueError: Sharp\Float::parse(): Argument #1 ($value) must hold a float, '.' given
NULL
ValueError: Sharp\Float::parse(): Argument #1 ($value) must hold a float, 'e3' given
NULL
ValueError: Sharp\Float::parse(): Argument #1 ($value) must hold a float, '1e' given
NULL
ValueError: Sharp\Float::parse(): Argument #1 ($value) must hold a float, '1e+' given
NULL
ValueError: Sharp\Float::parse(): Argument #1 ($value) must hold a float, 'NaN' given
NULL
ValueError: Sharp\Float::parse(): Argument #1 ($value) must hold a float, 'INF' given
NULL
ValueError: Sharp\Float::parse(): Argument #1 ($value) must hold a float, '-Infinity' given
NULL
ValueError: Sharp\Float::parse(): Argument #1 ($value) must hold a float, '0x1A' given
NULL
ValueError: Sharp\Float::parse(): Argument #1 ($value) must hold a float, '1.5abc' given
NULL
ValueError: Sharp\Float::parse(): Argument #1 ($value) must hold a float, '' given
NULL
ValueError: Sharp\Float::parse(): Argument #1 ($value) must be a string, null given
NULL
ValueError: Sharp\Float::parse(): Argument #1 ($value) must be a string, float given
NULL
ArithmeticError: Sharp\Float::parse(): Argument #1 ($value) must hold a float from -PHP_FLOAT_MAX to PHP_FLOAT_MAX, '1e999' given
NULL
ArithmeticError: Sharp\Float::parse(): Argument #1 ($value) must hold a float from -PHP_FLOAT_MAX to PHP_FLOAT_MAX, '-1e999' given
NULL
float(0)
