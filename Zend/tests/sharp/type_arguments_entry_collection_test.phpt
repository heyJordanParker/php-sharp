--TEST--
is, as and a match arm test every element of a collection from plain PHP and convert nothing, and test Any and a union by their members
--FILE--
<?php
require __DIR__ . '/type_arguments_entry.inc';

use App\Order;
use Checks\Tally;

echo Tally::kind([1, 2]), ' ', Tally::kind([1, 'x']), ' ', Tally::kind(['vip' => 1.5]), ' ', Tally::kind(['vip' => 1]), "\n";
var_dump(Tally::floats([1.5]), Tally::floats([1]), Tally::kept([3]), Tally::kept(['a']));
// `Any` takes every value but null, and a union takes a value one of its members takes.
echo Tally::sort(5), ' ', Tally::sort('x'), ' ', Tally::sort([1]), ' ', Tally::sort(new Order(1)), ' ', Tally::sort(1.5), ' ', Tally::sort(['x']), "\n";
var_dump(Tally::present(0), Tally::present(null), Tally::scalar('a'), Tally::scalar(1.5));
var_dump(Tally::taken(false), Tally::taken(null), Tally::narrowed(7), Tally::narrowed([7]));
// `is Iterable<T>` reads no element of a lazy source: a PHP# Iterable carries its element type, the type its
// source was checked against or its last lambda returns, and a plain PHP Traversable is only an `Iterable` of `Any?`.
function orders(): Generator
{
    echo "read\n";
    yield new Order(1);
}
var_dump(Tally::orders([new Order(1)]), Tally::orders([1]), Tally::ids(new Sharp\Set([1, 2])), Tally::ids(new Sharp\Set(['a'])));
var_dump(Tally::orders(orders()), Tally::anything(orders()));
var_dump(Tally::orders(Tally::held(orders())), Tally::orders(Tally::firstOf(orders())), Tally::ids(Tally::idsOf(orders())), Tally::orders(Tally::idsOf(orders())));
?>
--EXPECT--
ints other prices other
bool(true)
bool(false)
array(1) {
  [0]=>
  int(3)
}
NULL
scalar scalar entry entry other other
bool(true)
bool(false)
bool(true)
bool(false)
bool(false)
string(4) "none"
int(7)
NULL
bool(true)
bool(false)
bool(true)
bool(false)
bool(false)
bool(true)
bool(true)
bool(true)
bool(true)
bool(false)
