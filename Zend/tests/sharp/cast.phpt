--TEST--
PHP# (int) truncates a float and throws ArithmeticError on NaN, infinity and a float out of int range
--FILE--
<?php

require __DIR__ . '/vendor/autoload.php';

use Demo\Checkout;

function report(Throwable $e): void
{
    echo get_class($e), ': ', $e->getMessage(), ' in ', basename($e->getFile()), ' on line ', $e->getLine(), "\n";
}

var_dump(Checkout::cents(12.5), Checkout::cents(-0.75), Checkout::whole(-7.9), Checkout::whole(-9.2233720368547758E+18));
var_dump(Checkout::share(1, 4), Checkout::quantity(42));

foreach ([NAN, INF, -INF, 9.2233720368547758E+18, -1.0E+19] as $ratio) {
    try {
        Checkout::whole($ratio);
    } catch (ArithmeticError $e) {
        report($e);
    }
}

$sum = 0;
$thrown = 0;
for ($i = 0; $i < 200; $i++) {
    try {
        $sum += Checkout::whole($i < 150 ? $i + 0.5 : ($i % 2 ? NAN : 1.0E+300));
    } catch (ArithmeticError $e) {
        $thrown++;
    }
}
echo "sum $sum, thrown $thrown\n";

try {
    Checkout::cents(1.0E+17);
} catch (ArithmeticError $e) {
    report($e);
}

var_dump((int) 9.5, (int) "12abc");
?>
--EXPECT--
int(1250)
int(-75)
int(-7)
int(-9223372036854775808)
float(0.25)
string(2) "42"
ArithmeticError: The float NAN is not representable as an int in Checkout.sharp on line 27
ArithmeticError: The float INF is not representable as an int in Checkout.sharp on line 27
ArithmeticError: The float -INF is not representable as an int in Checkout.sharp on line 27
ArithmeticError: The float 9.223372036854776E+18 is not representable as an int in Checkout.sharp on line 27
ArithmeticError: The float -1.0E+19 is not representable as an int in Checkout.sharp on line 27
sum 11175, thrown 50
ArithmeticError: The float 1.0E+19 is not representable as an int in Checkout.sharp on line 22
int(9)
int(12)
