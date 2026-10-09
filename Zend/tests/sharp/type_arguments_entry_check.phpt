--TEST--
Plain PHP, call_user_func and array_map entering a PHP# method get a TypeError for an argument whose type arguments differ from the parameter's, a call from PHP# is never checked, a plain PHP generic class's type arguments are erased, an int given for a float becomes a float, a written Any? matches only Any?, and a type parameter plain PHP leaves unresolved matches any type at any depth
--FILE--
<?php
require __DIR__ . '/type_arguments.inc';
require __DIR__ . '/TypeArgumentsChecks.sharp';

use App\Box;
use App\Order;
use App\PaginatedList;
use App\Queue;
use Checks\Customer;
use Checks\Page;
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
attempt(fn () => Pages::count(Pages::boxedOrders()));
attempt(fn () => Pages::count(new Page()));

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
App\PaginatedList::paired(): Argument #1 ($item) must be of type App.Order, Checks\Customer given, called in %s on line %d
Checks\Pages::take(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given, called in %s on line %d
Checks\Pages::take(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given
took
kept
1
Checks\Pages::count(): Argument #1 ($page) must be of type Checks.Page<App.Box<?>>, Checks.Page<Any?> given, called in %s on line %d
float(2)
float(2)
float(2)
bool(true)
