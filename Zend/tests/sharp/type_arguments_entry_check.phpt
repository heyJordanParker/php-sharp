--TEST--
Plain PHP, call_user_func and array_map entering a PHP# method get a TypeError for an argument whose type arguments differ from the parameter's, a call from PHP# is never checked, and a plain PHP generic class's type arguments are erased
--FILE--
<?php
require __DIR__ . '/type_arguments.inc';
require __DIR__ . '/TypeArgumentsChecks.sharp';

use App\Box;
use App\Order;
use App\PaginatedList;
use App\Queue;
use Checks\Customer;
use Checks\Pages;
use Lib\Holder;

function attempt(callable $call): void
{
    try {
        echo $call(), "\n";
    } catch (TypeError $error) {
        echo $error->getMessage(), "\n";
    }
}

$customers = Pages::customers();

attempt(fn () => Pages::take(Queue::orders()));
attempt(fn () => Pages::take(Queue::page()));
attempt(fn () => Pages::take($customers));
attempt(fn () => Pages::take(new PaginatedList([])));
attempt(fn () => Pages::open(new Box()));
attempt(fn () => Pages::open(Queue::box()));
attempt(fn () => get_class(Queue::orders()->paired(new Order(1))));
attempt(fn () => get_class(Queue::orders()->paired(new Customer(1))));
attempt(fn () => call_user_func([Pages::class, 'take'], $customers));
attempt(fn () => array_map([Pages::class, 'take'], [$customers])[0]);
attempt(fn () => Pages::relay([$customers]));
attempt(fn () => Pages::keep(new Holder()));
?>
--EXPECTF--
took
took
Checks\Pages::take(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given, called in %s on line %d
Checks\Pages::take(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<App.DatabaseEntity> given, called in %s on line %d
opened
opened
App\Pair
App\PaginatedList::paired(): Argument #1 ($item) must be of type App.Order, Checks\Customer given, called in %s on line %d
Checks\Pages::take(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given, called in %s on line %d
Checks\Pages::take(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given
took
kept
