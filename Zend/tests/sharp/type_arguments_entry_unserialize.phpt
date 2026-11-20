--TEST--
unserialize builds a PHP# object through its constructor, which checks each field, spelled with the type arguments it reads after them, and a back-reference to another object of the data
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
attempt(fn () => unserialize(str_replace('s:5:"items";a:0:{}', 's:5:"items";R:2;', $serialized))->held());
attempt(fn () => unserialize(str_replace('a:0:{}', 'a:1:{i:0;s:1:"x";}', serialize(new Tally()))));
?>
--EXPECT--
bool(true)
own: expected App.Box<App.Order>?, got object App.Box<Checks.Customer>
items: expected List<App.Order>, got object App.Box<App.Order>
amounts[0]: expected float, got string 'x'
