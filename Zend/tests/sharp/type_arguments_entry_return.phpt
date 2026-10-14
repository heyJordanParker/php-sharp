--TEST--
A plain PHP call's return gets a TypeError wherever PHP# takes it when its type arguments differ from the declared type's
--FILE--
<?php
require __DIR__ . '/type_arguments_entry.inc';

use App\Queue;
use Checks\Pages;
use Checks\Shelf;
use Lib\Pager;

$shelf = Shelf::filled();
foreach ([Pages::customers(), Queue::orders()] as $next) {
    Pager::$next = $next;
    attempt(fn () => Shelf::read());
    attempt(fn () => Shelf::untyped());
    attempt(fn () => Shelf::passed());
    attempt(fn () => get_class(Shelf::given()));
    attempt(fn () => $shelf->stored());
}
?>
--EXPECT--
Lib\Pager::orders(): Return value must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> returned
Lib\Pager::orders(): Return value must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> returned
Lib\Pager::orders(): Return value must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> returned
Lib\Pager::orders(): Return value must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> returned
Lib\Pager::orders(): Return value must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> returned
read
untyped
took
App\PaginatedList
stored
