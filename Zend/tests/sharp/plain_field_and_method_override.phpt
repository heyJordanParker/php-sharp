--TEST--
A PHP# class overrides the method or the field of a plain PHP parent that declares both with one name, as Eloquent's $casts and casts(), and a call runs the method and a read reads the field, from the parent's code and from PHP# code
--FILE--
<?php
require __DIR__ . '/harness/Casting.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('Casts');

$order = new App\Casts\Order();
var_dump($order->getCasts(), $order->called(), $order->read());

$invoice = new App\Casts\Invoice();
var_dump($invoice->getCasts(), $invoice->called(), $invoice->read());
?>
--EXPECT--
array(2) {
  ["id"]=>
  string(3) "int"
  ["paid"]=>
  string(4) "bool"
}
array(1) {
  ["paid"]=>
  string(4) "bool"
}
array(1) {
  ["id"]=>
  string(3) "int"
}
array(1) {
  ["due"]=>
  string(4) "date"
}
array(0) {
}
array(1) {
  ["due"]=>
  string(4) "date"
}
