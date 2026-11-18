--TEST--
PHP# any code extends Class<T>, whose own members win, takes an extension's receiver by ref to change it in place, and an extension reaches an internal member of its namespace
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

echo Extended\Ticket::run(), "\n";
?>
--EXPECT--
<Extended.Ticket> Extended.Ticket 2 1 7
