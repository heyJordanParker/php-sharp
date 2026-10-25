--TEST--
Plain PHP takes a reference to a property whose type names its class's type parameter, a write through it takes any value, and PHP# code checks the property with the object's type arguments when it next reads it
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
attempt(fn () => $rack->held());
attempt(function () use ($rack) {
    $own = &$rack->own;
    $own = Rack::customerBox();
    return get_class($rack->own);
});
attempt(fn () => get_class($rack->kept()));

// Each way plain PHP takes a reference to such a property.
$rack = Rack::orders();
attempt(function () use ($rack) {
    foreach ($rack as &$value) {
    }
    return 'foreach';
});
attempt(fn () => array_walk($rack, fn () => null) ? 'array_walk' : 'no array_walk');
attempt(function () use ($rack) {
    $box = Rack::box();
    $rack->own = &$box;
    return get_class($rack->kept());
});
attempt(function () use ($rack) {
    foreach (@new ArrayIterator($rack) as &$value) {
    }
    return 'ArrayIterator';
});
// A property with a set body takes no reference, as stock PHP refuses one to a hooked property.
attempt(function () {
    foreach (Ledger::orders() as &$value) {
    }
    return 'a hooked object';
});
?>
--EXPECT--
1
Reference held by property Checks\Rack::$items of type List<App.Order> holds array: [0] expected App.Order, got string 'no order'
App\Box
Reference held by property Checks\Rack::$own of type App.Box<App.Order>? holds App.Box<Checks.Customer>
foreach
array_walk
App\Box
ArrayIterator
Cannot create reference to property Checks\Ledger::$kept
