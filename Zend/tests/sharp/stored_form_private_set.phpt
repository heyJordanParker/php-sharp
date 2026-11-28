--TEST--
A class's stored form holds every stored field, spec section 10: Json.encode writes a property with a private set, a machine's among them, and Json.parse<T> reads it back
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use DataInOut\Accounts;

$text = '{"id":7,"balance":"12.50","status":"active"}';
echo Accounts::encoded(Accounts::decoded($text)), "\n";
echo get_class(Accounts::statusOf($text)), "\n";
?>
--EXPECT--
{"id":7,"balance":"12.50","status":"active"}
DataInOut\AccountStatus\Active
