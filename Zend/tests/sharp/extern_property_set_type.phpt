--TEST--
Property.set of an extern property refuses a value its type doesn't take with the TypeError Property.set gives any property, before the plain PHP parent's __set runs, and a value its type takes reaches __set under its key
--FILE--
<?php
require __DIR__ . '/harness/Columns.inc';
require __DIR__ . '/vendor/autoload.php';

$payment = new ExternProperties\Payment();
$totalCents = ExternProperties\Payment::property('totalCents');
try {
    $totalCents->set($payment, 'not a number');
} catch (TypeError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
var_dump($payment->columns());
$totalCents->set($payment, 250);
var_dump($payment->columns());
?>
--EXPECT--
TypeError: Cannot assign string to property ExternProperties\Payment::$totalCents of type int
array(0) {
}
__set total_cents
array(1) {
  ["total_cents"]=>
  int(250)
}
