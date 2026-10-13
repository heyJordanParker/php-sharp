--TEST--
Plain PHP, call_user_func and array_map entering a PHP# method, an event's constructor, a named constructor, an operator or a lambda get a TypeError for an argument whose type arguments differ from the parameter's, as a plain PHP call's return does wherever PHP# takes it and a PHP# property for a write from plain PHP, a static one's public or protected also through a subclass, a reference and reflection, a call or write from PHP# is never checked, a plain PHP generic class's type arguments are erased, an int given for a float becomes a float, a written Any? matches only Any?, and a type parameter plain PHP leaves unresolved matches any type at any depth
--FILE--
<?php
require __DIR__ . '/type_arguments.inc';
require __DIR__ . '/harness/Pager.inc';
require __DIR__ . '/TypeArgumentsChecks.sharp';

use App\Box;
use App\Order;
use App\PaginatedList;
use App\Queue;
use Checks\Customer;
use Checks\Ledger;
use Checks\Page;
use Checks\Paged;
use Checks\Pages;
use Checks\Shelf;
use Lib\Holder;
use Lib\Pager;

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
attempt(fn () => Pages::count(Pages::boxedOrders()));
attempt(fn () => Pages::count(new Page()));
foreach ([Queue::orders(), $customers] as $page) {
    attempt(fn () => get_class(new Paged($page)));
    attempt(fn () => get_class(Ledger::of($page)));
    attempt(fn () => get_class(Ledger::op_Addition(new Ledger(1), $page)));
    attempt(fn () => Pages::taker()($page));
}

$shelf = Shelf::filled();
foreach ([$customers, Queue::orders()] as $next) {
    Pager::$next = $next;
    attempt(fn () => Shelf::read());
    attempt(fn () => Shelf::untyped());
    attempt(fn () => Shelf::passed());
    attempt(fn () => get_class(Shelf::given()));
    attempt(fn () => $shelf->stored());
}
attempt(function () use ($shelf, $customers) {
    $shelf->page = $customers;
});
attempt(fn () => (new ReflectionProperty(Shelf::class, 'page'))->setValue($shelf, $customers));
attempt(function () use ($shelf) {
    $shelf->page = Queue::orders();
    return 'written';
});

final class PlainShelf extends Shelf
{
    public static function keep(object $page): string
    {
        static::$kept = $page;
        return 'kept';
    }
}

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

foreach ([Pages::floats(), Pages::nullableFloats(), Pages::floatsOrStrings()] as $cell) {
    $cell->put(2);
    var_dump($cell->get());
}
$floats = Pages::floats();
$floats->put(2);
var_dump(Pages::two($floats));
?>
--EXPECTF--
took
took
Checks\Pages::take(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given, called in %s on line %d
Checks\Pages::take(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<App.DatabaseEntity> given, called in %s on line %d
opened
Checks\Pages::open(): Argument #1 ($box) must be of type App.Box<Any?>, App.Box<int> given, called in %s on line %d
App\Pair
App\PaginatedList::paired(): Argument #1 ($item) must be of type App.Order, Checks.Customer given, called in %s on line %d
Checks\Pages::take(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given, called in %s on line %d
Checks\Pages::take(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given
took
kept
1
Checks\Pages::count(): Argument #1 ($page) must be of type Checks.Page<App.Box<?>>, Checks.Page<Any?> given, called in %s on line %d
Checks\Paged
Checks\Ledger
Checks\Ledger
took
Checks\Paged::__construct(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given, called in %s on line %d
Checks\Ledger::of(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given, called in %s on line %d
Checks\Ledger::op_Addition(): Argument #2 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given, called in %s on line %d
Checks\Pages::{closure:Checks\Pages::taker():%d}(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given, called in %s on line %d
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
Cannot assign App.PaginatedList<Checks.Customer> to property Checks\Shelf::$page of type App.PaginatedList<App.Order>?
Cannot assign App.PaginatedList<Checks.Customer> to property Checks\Shelf::$page of type App.PaginatedList<App.Order>?
written
Cannot assign App.PaginatedList<Checks.Customer> to property Checks\Shelf::$latest of type App.PaginatedList<App.Order>?
Cannot assign App.PaginatedList<Checks.Customer> to property Checks\Shelf::$kept of type App.PaginatedList<App.Order>?
Cannot assign App.PaginatedList<Checks.Customer> to property Checks\Shelf::$latest of type App.PaginatedList<App.Order>?
Cannot assign App.PaginatedList<Checks.Customer> to reference held by property Checks\Shelf::$latest of type App.PaginatedList<App.Order>?
written
kept
kept both
float(2)
float(2)
float(2)
bool(true)
