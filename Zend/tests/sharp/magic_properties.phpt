--TEST--
PHP# reads the properties __get serves on a plain PHP class, typed or not, or through a PHP# subclass's @property-read line or extern property, and its method of that name where the code takes a function
--FILE--
<?php
require __DIR__ . '/harness/Booking.inc';
require __DIR__ . '/vendor/autoload.php';

$greeter = new MagicProperties\Greeter();
$booking = new Lib\Booking();
var_dump($greeter->typed($booking), $greeter->untyped($booking), $greeter->taken($booking), $greeter->called($booking));
$stay = new MagicProperties\Stay();
var_dump($stay->typed(), $stay->declared(), $stay->taken(), $stay->called());
?>
--EXPECT--
string(3) "Ada"
string(5) "Grace"
string(17) "the host relation"
string(17) "the host relation"
string(3) "Ada"
string(5) "Grace"
string(17) "the host relation"
string(17) "the host relation"
