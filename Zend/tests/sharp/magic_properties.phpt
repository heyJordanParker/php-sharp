--TEST--
PHP# reads the properties a plain PHP class's __get serves, typed or not, and its method of that name where the code takes a function
--FILE--
<?php
require __DIR__ . '/harness/Booking.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('MagicProperties');

$greeter = new MagicProperties\Greeter();
$booking = new Lib\Booking();
var_dump($greeter->typed($booking), $greeter->untyped($booking), $greeter->taken($booking), $greeter->called($booking));
?>
--EXPECT--
string(3) "Ada"
string(5) "Grace"
string(17) "the host relation"
string(17) "the host relation"
