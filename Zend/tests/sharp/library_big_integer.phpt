--TEST--
PHP# BigInteger holds an integer of any size, as .NET's BigInteger: past Int.MAX it goes on, toIntOrNull gives null outside an int's range, / and % round toward 0 as an int's do, pow refuses a negative exponent, and toString writes any base from 2 to 36
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\NumberQueries;

function refused(Closure $call): void
{
    try {
        $call();
    } catch (ValueError|ArithmeticError|LogicException $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}

echo NumberQueries::pastLargest(), ' ';
var_dump(NumberQueries::pastLargestAsInt());
echo json_encode(NumberQueries::integers('-7', '2')), "\n", json_encode(NumberQueries::integers('100000000000000000000', '3')), "\n";
echo json_encode(NumberQueries::integersCompared('10', '9')), ' ', json_encode(NumberQueries::integersCompared('-5', '-5')), "\n";
echo NumberQueries::power('2', 100), ' ', NumberQueries::power('-3', 3), ' ', NumberQueries::power('5', 0), "\n";
echo NumberQueries::inRadix('255', 16), ' ', NumberQueries::inRadix('-255', 2), ' ', NumberQueries::inRadix('0', 7), ' ';
echo NumberQueries::inRadix('18446744073709551616', 16), ' ', NumberQueries::inRadix('35', 36), ' ', NumberQueries::inRadix('-12', 10), "\n";
var_dump(NumberQueries::asInt('-9223372036854775808'), NumberQueries::asInt('-9223372036854775809'));
echo json_encode(array_map(NumberQueries::tryInteger(...), ['007', '-0', '+12', '1.0', '', '0x1f'])), "\n";
refused(fn () => NumberQueries::power('2', -1));
refused(fn () => NumberQueries::inRadix('1', 37));
refused(fn () => NumberQueries::integer('x'));
refused(fn () => NumberQueries::integers('1', '0'));
?>
--EXPECT--
9223372036854775808 NULL
["-5","-9","-14","-3","-1"]
["100000000000000000003","99999999999999999997","300000000000000000000","33333333333333333333","1"]
[1,1,0] [0,0,1]
1267650600228229401496703205376 -27 1
ff -11111111 0 10000000000000000 z -12
int(-9223372036854775808)
NULL
["7","0","12",null,null,null]
ArithmeticError: BigInteger.pow: -1 is a negative exponent, and an integer raised to it is no integer
ValueError: BigInteger.toString takes a base from 2 to 36, and 37 is not one.
LogicException: BigInteger.parse: "x" is not an integer, such as "-12"
DivisionByZeroError: Division by zero
