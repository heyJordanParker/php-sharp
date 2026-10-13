--TEST--
PHP# Object holds any object, runs as PHP's object, and is narrowed by is
--FILE--
<?php

require __DIR__ . '/Ids.sharp';

$ticket = new Demo\Ticket();
$plain = new stdClass();

var_dump(Demo\Ids::identity($ticket) === spl_object_id($ticket));
var_dump(Demo\Ids::either(null, $plain) === $plain);
var_dump(Demo\Ids::either($ticket, $plain) === $ticket);
echo Demo\Ids::describe($ticket), "\n";
echo Demo\Ids::describe($plain), "\n";

try {
    Demo\Ids::identity(5);
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

try {
    Demo\Ids::either(null, null);
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECTF--
bool(true)
bool(true)
bool(true)
T7
other
Demo\Ids::identity(): Argument #1 ($value) must be of type object, int given, called in %s on line %d
Demo\Ids::either(): Argument #2 ($fallback) must be of type object, null given, called in %s on line %d
