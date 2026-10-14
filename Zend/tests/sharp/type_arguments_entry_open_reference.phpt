--TEST--
Plain PHP can't take a reference to a property whose type names its class's type parameter, which would hold no type to check a later write against, and its append is unchecked
--FILE--
<?php
require __DIR__ . '/type_arguments_entry.inc';

use Checks\Ledger;
use Checks\Rack;

$rack = Rack::orders();
attempt(function () use ($rack) {
    $rack->items[] = 'no order';
    return count($rack->items);
});
attempt(function () use ($rack) {
    $own = &$rack->own;
});
attempt(function () use ($rack) {
    foreach ($rack as &$value) {
    }
});
attempt(fn () => array_walk($rack, fn () => null));
attempt(function () use ($rack) {
    $box = Rack::box();
    $rack->own = &$box;
});
attempt(function () use ($rack) {
    foreach (@new ArrayIterator($rack) as &$value) {
    }
});
attempt(function () {
    foreach (Ledger::orders() as &$value) {
    }
});
?>
--EXPECT--
1
Cannot take a reference to `Checks\Rack::$own`: its type depends on the object's type arguments
Cannot take a reference to `Checks\Rack::$own`: its type depends on the object's type arguments
Cannot take a reference to `Checks\Rack::$own`: its type depends on the object's type arguments
Cannot take a reference to `Checks\Rack::$own`: its type depends on the object's type arguments
Cannot take a reference to `Checks\Rack::$own`: its type depends on the object's type arguments
Cannot take a reference to `Checks\Ledger::$first`: its type depends on the object's type arguments
