--TEST--
Plain PHP, call_user_func and array_map entering a PHP# method, an event's constructor, a named constructor, an operator or a lambda get a TypeError for an argument whose type arguments differ from the parameter's
--FILE--
<?php
require __DIR__ . '/type_arguments_entry.inc';

use App\Order;
use App\PaginatedList;
use App\Queue;
use Checks\Customer;
use Checks\Paged;
use Checks\Pages;
use Checks\Purse;

$customers = Pages::customers();

attempt(fn () => Pages::take(Queue::orders()));
attempt(fn () => Pages::take(Queue::page()));
attempt(fn () => Pages::take($customers));
attempt(fn () => Pages::take(new PaginatedList([])));
attempt(fn () => get_class(Queue::orders()->paired(new Order(1))));
attempt(fn () => get_class(Queue::orders()->paired(new Customer(1))));
attempt(fn () => call_user_func([Pages::class, 'take'], $customers));
attempt(fn () => array_map([Pages::class, 'take'], [$customers])[0]);
attempt(fn () => Pages::relay([$customers]));
foreach ([Queue::orders(), $customers] as $page) {
    attempt(fn () => get_class(new Paged($page)));
    attempt(fn () => get_class(Purse::of($page)));
    attempt(fn () => get_class(Purse::op_Addition(new Purse(1), $page)));
    attempt(fn () => Pages::taker()($page));
}
?>
--EXPECTF--
took
took
Checks\Pages::take(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given, called in %s on line %d
Checks\Pages::take(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<App.DatabaseEntity> given, called in %s on line %d
App\Pair
App\PaginatedList::paired(): Argument #1 ($item) must be of type App.Order, Checks.Customer given, called in %s on line %d
Checks\Pages::take(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given, called in %s on line %d
Checks\Pages::take(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given
Checks\Pages::relay(): Argument #1 ($pages) must be of type List<App.PaginatedList<App.Order>>, array given, called in %s on line %d
Checks\Paged
Checks\Purse
Checks\Purse
took
Checks\Paged::__construct(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given, called in %s on line %d
Checks\Purse::of(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given, called in %s on line %d
Checks\Purse::op_Addition(): Argument #2 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given, called in %s on line %d
Checks\Pages::{closure:Checks\Pages::taker():%d}(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given, called in %s on line %d
