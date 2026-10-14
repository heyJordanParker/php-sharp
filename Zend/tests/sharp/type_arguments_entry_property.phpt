--TEST--
A PHP# property gets a TypeError for a write from plain PHP, reflection included, whose type arguments differ from the property's
--FILE--
<?php
require __DIR__ . '/type_arguments_entry.inc';

use App\Queue;
use Checks\Pages;
use Checks\Shelf;

$shelf = Shelf::filled();
$customers = Pages::customers();
attempt(function () use ($shelf, $customers) {
    $shelf->page = $customers;
});
attempt(fn () => (new ReflectionProperty(Shelf::class, 'page'))->setValue($shelf, $customers));
attempt(function () use ($shelf) {
    $shelf->page = Queue::orders();
    return 'written';
});
?>
--EXPECT--
Cannot assign App.PaginatedList<Checks.Customer> to property Checks\Shelf::$page of type App.PaginatedList<App.Order>?
Cannot assign App.PaginatedList<Checks.Customer> to property Checks\Shelf::$page of type App.PaginatedList<App.Order>?
written
