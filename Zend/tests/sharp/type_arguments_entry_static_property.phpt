--TEST--
A PHP# static property, public or protected, gets a TypeError for a write from plain PHP whose type arguments differ from its own, also through a subclass, a reference and reflection, and a write from PHP# is never checked
--FILE--
<?php
require __DIR__ . '/type_arguments_entry.inc';

use App\Queue;
use Checks\Pages;
use Checks\Shelf;

final class PlainShelf extends Shelf
{
    public static function keep(object $page): string
    {
        static::$kept = $page;
        return 'kept';
    }
}

$customers = Pages::customers();
attempt(function () use ($customers) {
    Shelf::$latest = $customers;
});
attempt(fn () => PlainShelf::keep($customers));
attempt(fn () => (new ReflectionProperty(Shelf::class, 'latest'))->setValue(null, $customers));
attempt(function () use ($customers) {
    $latest = &Shelf::$latest;
    $latest = $customers;
});
attempt(function () {
    Shelf::$latest = Queue::orders();
    return 'written';
});
attempt(fn () => PlainShelf::keep(Queue::orders()));
attempt(fn () => Shelf::keepBoth(Queue::orders()));
?>
--EXPECT--
Cannot assign App.PaginatedList<Checks.Customer> to property Checks\Shelf::$latest of type App.PaginatedList<App.Order>?
Cannot assign App.PaginatedList<Checks.Customer> to property Checks\Shelf::$kept of type App.PaginatedList<App.Order>?
Cannot assign App.PaginatedList<Checks.Customer> to property Checks\Shelf::$latest of type App.PaginatedList<App.Order>?
Cannot assign App.PaginatedList<Checks.Customer> to reference held by property Checks\Shelf::$latest of type App.PaginatedList<App.Order>?
written
kept
kept both
