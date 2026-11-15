--TEST--
PHP# Decimal is exact, as Java's BigDecimal: 0.1 + 0.2 == 0.3, +, - and * keep every digit, == and <=> compare the number whatever its scale, parse keeps the scale it reads, and divide and round take a scale and each of the seven RoundingModes, deciding a halfway quotient by its exact remainder
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Math.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/RoundingMode.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/Decimal.sharp';
require __DIR__ . '/NumberQueries.sharp';

use Demo\NumberQueries;
use Sharp\RoundingMode;

function refused(Closure $call): void
{
    try {
        $call();
    } catch (ValueError|DivisionByZeroError $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}

var_dump(NumberQueries::tenthsAddUp());
echo NumberQueries::third(), "\n";
echo json_encode(NumberQueries::arithmetic('1.50', '0.25')), ' ', json_encode(NumberQueries::arithmetic('-2', '0.5')), "\n";
echo json_encode(NumberQueries::compared('1.5', '1.50')), ' ', json_encode(NumberQueries::compared('-0.1', '0.01')), "\n";
echo NumberQueries::scale('1.50'), ' ', NumberQueries::scale('7'), ' ', NumberQueries::of(-42), "\n";
echo json_encode(array_map(NumberQueries::tryParse(...), ['007.50', '-0.00', '+3', '1e3', ' 1', '1.', '.5', ''])), "\n";
foreach ([['1', '8', 2], ['-1', '8', 2], ['1', '3', 0], ['1.0001', '8', 2], ['-1', '300', 2]] as [$a, $b, $scale]) {
    $quotients = [];
    foreach (RoundingMode::cases() as $mode) {
        $quotients[] = $mode->name . ' ' . NumberQueries::divide($a, $b, $scale, $mode);
    }
    echo "$a / $b at $scale: ", implode(', ', $quotients), "\n";
}
echo NumberQueries::round('2.5', 0, RoundingMode::HalfEven), ' ', NumberQueries::round('3.5', 0, RoundingMode::HalfEven), ' ';
echo NumberQueries::round('-2.5', 0, RoundingMode::HalfUp), ' ', NumberQueries::roundHalfEven('1.5', 3), ' ';
echo NumberQueries::round('2.345', 2, RoundingMode::HalfDown), ' ', NumberQueries::round('2.3451', 2, RoundingMode::HalfDown), "\n";
refused(fn () => NumberQueries::parse('abc'));
refused(fn () => NumberQueries::divide('1', '0.00', 2, RoundingMode::HalfEven));
refused(fn () => NumberQueries::divide('1', '3', -1, RoundingMode::HalfEven));
refused(fn () => NumberQueries::round('1', -1, RoundingMode::HalfEven));
?>
--EXPECT--
bool(true)
3.33
["1.75","1.25","0.3750"] ["-1.5","-2.5","-1.0"]
[0,0,1,1] [-1,1,0,0]
2 0 -42
["7.50","0.00","3",null,null,null,null,null]
1 / 8 at 2: HalfEven 0.12, HalfUp 0.13, HalfDown 0.12, Up 0.13, Down 0.12, Ceiling 0.13, Floor 0.12
-1 / 8 at 2: HalfEven -0.12, HalfUp -0.13, HalfDown -0.12, Up -0.13, Down -0.12, Ceiling -0.12, Floor -0.13
1 / 3 at 0: HalfEven 0, HalfUp 0, HalfDown 0, Up 1, Down 0, Ceiling 1, Floor 0
1.0001 / 8 at 2: HalfEven 0.13, HalfUp 0.13, HalfDown 0.13, Up 0.13, Down 0.12, Ceiling 0.13, Floor 0.12
-1 / 300 at 2: HalfEven 0.00, HalfUp 0.00, HalfDown 0.00, Up -0.01, Down 0.00, Ceiling 0.00, Floor -0.01
2 4 -3 1.500 2.34 2.35
ValueError: Decimal.parse: "abc" is not a decimal number, such as "-12.50"
DivisionByZeroError: Decimal.divide: the divisor is 0
ValueError: Decimal.divide takes a scale of 0 or more, and -1 is not one.
ValueError: Decimal.round takes a scale of 0 or more, and -1 is not one.
