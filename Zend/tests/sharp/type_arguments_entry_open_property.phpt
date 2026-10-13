--TEST--
A property whose type names its class's type parameter checks a plain PHP write, reflection and a set body included, spelled with the object's type arguments
--FILE--
<?php
require __DIR__ . '/type_arguments_entry.inc';

use Checks\Cell;
use Checks\Ledger;
use Checks\Pages;
use Checks\Rack;

$rack = Rack::orders();
attempt(function () use ($rack) {
    $rack->own = Rack::customerBox();
});
attempt(function () use ($rack) {
    $rack->own = Rack::box();
    return 'owned';
});
attempt(fn () => (new ReflectionProperty(Cell::class, 'value'))->setValue(Pages::floats(), 'none'));
$cell = Pages::floats();
(new ReflectionProperty(Cell::class, 'value'))->setValue($cell, 3);
var_dump($cell->get());
attempt(function () {
    Ledger::orders()->kept = Rack::customerBox();
});
?>
--EXPECT--
Cannot assign App.Box<Checks.Customer> to property Checks\Rack::$own of type App.Box<App.Order>?
owned
Cannot assign string to property Checks\Cell::$value of type float
float(3)
Cannot assign App.Box<Checks.Customer> to property Checks\Ledger::$kept of type App.Box<App.Order>?
