--TEST--
unserialize checks each PHP# property it fills, spelled with the type arguments it reads after them, and refuses a reference to a property whose type names its class's type parameter
--FILE--
<?php
require __DIR__ . '/type_arguments_entry.inc';

use Checks\Rack;
use Checks\Tally;

$stored = Rack::orders();
$stored->own = Rack::box();
$serialized = serialize($stored);
var_dump(unserialize($serialized) == $stored);
attempt(fn () => unserialize(preg_replace('/s:9:"App\.Order"/', 's:15:"Checks.Customer"', $serialized, 1)));
attempt(fn () => unserialize(str_replace('s:5:"items";a:0:{}', 's:5:"items";R:2;', $serialized)));
attempt(fn () => unserialize(str_replace('a:0:{}', 'a:1:{i:0;s:1:"x";}', serialize(new Tally()))));
?>
--EXPECT--
bool(true)
Cannot assign App.Box<Checks.Customer> to property Checks\Rack::$own of type App.Box<App.Order>?
Cannot take a reference to `Checks\Rack::$own`: its type depends on the object's type arguments
Cannot assign array to property Checks\Tally::$amounts of type List<float>: [0] expected float, got string 'x'
