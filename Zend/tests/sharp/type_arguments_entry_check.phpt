--TEST--
Plain PHP, call_user_func and array_map entering a PHP# method, an event's constructor, a named constructor, an operator or a lambda get a TypeError for an argument whose type arguments differ from the parameter's, as a plain PHP call's return does wherever PHP# takes it and a PHP# property for a write from plain PHP, through a set body too, spelled with the object's type arguments where its type names its class's type parameter, which also refuses plain PHP a reference to it, unserialize included, an append to a List property unchecked, a static one's public or protected also through a subclass, a reference and reflection, a call or write from PHP# is never checked, a plain PHP generic class's type arguments are erased, an int given for a float becomes a float, a written Any? matches only Any?, a type parameter plain PHP leaves unresolved matches any type at any depth, an out or in type parameter takes a subtype or a supertype, a List, Map or Set is checked element by element with its ints in a float's place made floats in a copy, a class value by its class and the type arguments it carries or its class's header gives, is, as and match test every element and convert nothing, test Any and a union by their members, and Sharp\Internal\accepts collects every bad value at its path
--FILE--
<?php
require __DIR__ . '/type_arguments.inc';
require __DIR__ . '/harness/Pager.inc';
require __DIR__ . '/TypeArgumentsChecks.sharp';

use App\Box;
use App\Order;
use App\PaginatedList;
use App\Queue;
use Checks\Cell;
use Checks\Customer;
use Checks\Ledger;
use Checks\Page;
use Checks\Paged;
use Checks\Pages;
use Checks\Purse;
use Checks\Rack;
use Checks\Shelf;
use Checks\Stream;
use Checks\Tally;
use Lib\Holder;
use Lib\Pager;

function attempt(callable $call): void
{
    try {
        echo $call(), "\n";
    } catch (Error $error) {
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
    attempt(fn () => get_class(Purse::of($page)));
    attempt(fn () => get_class(Purse::op_Addition(new Purse(1), $page)));
    attempt(fn () => Pages::taker()($page));
}
// An `out` type parameter takes a subtype and an `in` one a supertype, at any depth, and a plain PHP object's bound
// takes neither.
attempt(fn () => Pages::read(Pages::orders()));
attempt(fn () => Pages::nested(Pages::streams()));
attempt(fn () => Pages::read(new Stream()));
attempt(fn () => Pages::drain(Pages::entities()));
attempt(fn () => Pages::drain(Pages::customerDrain()));
var_dump(Pages::fed(Pages::orders()), Pages::fed(Pages::customerStream()), Pages::fed(Pages::streams()));

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

// A collection from plain PHP is checked element by element, and an int in a float's place becomes a float in a copy.
attempt(fn () => Tally::sizes([1, 2], ['vip' => 1.5, 5 => 2.0], ['a' => 'a']));
attempt(fn () => Tally::sizes([1, 'a'], [], []));
attempt(fn () => Tally::sizes(['first' => 1], [], []));
attempt(fn () => Tally::sizes([], ['vip' => 'cheap'], []));
attempt(fn () => Tally::sizes([], [], ['a' => 7]));
attempt(fn () => Tally::rows([[1], [2, 'x']]));
$amounts = [1, 2.5];
var_dump(Tally::first($amounts), $amounts[0]);
// A list a plain PHP docblock claims is checked where PHP# takes it.
Pager::$sizes = [3, 7];
attempt(fn () => Tally::largest());
Pager::$sizes = [3, 'seven'];
attempt(fn () => Tally::largest());
// `is`, `as` and a `match` arm test every element, and never turn an int into a float.
echo Tally::kind([1, 2]), ' ', Tally::kind([1, 'x']), ' ', Tally::kind(['vip' => 1.5]), ' ', Tally::kind(['vip' => 1]), "\n";
var_dump(Tally::floats([1.5]), Tally::floats([1]), Tally::kept([3]), Tally::kept(['a']));
// `Any` takes every value but null, and a union takes a value one of its members takes.
echo Tally::sort(5), ' ', Tally::sort('x'), ' ', Tally::sort([1]), ' ', Tally::sort(new Order(1)), ' ', Tally::sort(1.5), ' ', Tally::sort(['x']), "\n";
var_dump(Tally::present(0), Tally::present(null), Tally::scalar('a'), Tally::scalar(1.5));
var_dump(Tally::taken(false), Tally::taken(null), Tally::narrowed(7), Tally::narrowed([7]));
// An append writes an element, which plain PHP never checks.
$tally = new Tally();
attempt(function () use ($tally) {
    $tally->amounts[] = 2.5;
    return json_encode($tally->amounts, JSON_PRESERVE_ZERO_FRACTION);
});
// A whole write walks every element, and an int in a float's place becomes a float.
attempt(function () use ($tally) {
    $tally->amounts = [1, 2.5];
    return json_encode($tally->amounts, JSON_PRESERVE_ZERO_FRACTION);
});
attempt(function () use ($tally) {
    $tally->amounts = [1, 'x'];
});

// A property whose type names its class's type parameter takes the object's type arguments.
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
// A write through a set body is checked on the value it stores.
attempt(function () {
    Ledger::orders()->kept = Rack::customerBox();
});
// Its append is as unchecked, and a reference would hold no type to check a later write against, so plain PHP can't
// take one.
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
// unserialize checks each property it fills, spelled with the type arguments it reads after them, and refuses a
// reference to such a property.
$stored = Rack::orders();
$stored->own = Rack::box();
$serialized = serialize($stored);
var_dump(unserialize($serialized) == $stored);
attempt(fn () => unserialize(preg_replace('/s:9:"App\.Order"/', 's:15:"Checks.Customer"', $serialized, 1)));
attempt(fn () => unserialize(str_replace('s:5:"items";a:0:{}', 's:5:"items";R:2;', $serialized)));
attempt(fn () => unserialize(str_replace('a:0:{}', 'a:1:{i:0;s:1:"x";}', serialize(new Tally()))));

// A struct's parse collects every bad value at its path, through the same walk.
$problems = [];
$value = ['lines' => [1, 'two', str_repeat('x', 45)], 'prices' => ['vip' => 'cheap', 3 => 1]];
var_dump(\Sharp\Internal\accepts('Map<string, List<int>>', $value, 'order', $problems));
var_dump($problems);
$problems = [];
$value = ['vip' => 1, 'base' => 2.5];
$shared = $value;
var_dump(\Sharp\Internal\accepts('Map<string, float>', $value, 'prices', $problems), $problems, $value['vip'], $shared['vip']);
$problems = [];
$value = ['vip' => 1];
var_dump(\Sharp\Internal\accepts('Map<int, int>?', $value, 'counts', $problems), $problems);
// A class value names its class or a class whose header fixes the type arguments, and a name carries no others.
$problems = [];
$value = ['page' => 'App\OrderPage', 'raw' => 'App\PaginatedList', 'none' => 7];
var_dump(\Sharp\Internal\accepts('Map<string, Class<App.PaginatedList<App.Order>>>', $value, 'kinds', $problems), $problems);
foreach (['List<', 'List<$0>'] as $type) {
    try {
        \Sharp\Internal\accepts($type, $value, 'x', $problems);
    } catch (ValueError $error) {
        echo $error->getMessage(), "\n";
    }
}
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
Checks\Pages::relay(): Argument #1 ($pages) must be of type List<App.PaginatedList<App.Order>>, array given, called in %s on line %d
kept
1
Checks\Pages::count(): Argument #1 ($page) must be of type Checks.Page<App.Box<?>>, Checks.Page<Any?> given, called in %s on line %d
Checks\Paged
Checks\Purse
Checks\Purse
took
Checks\Paged::__construct(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given, called in %s on line %d
Checks\Purse::of(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given, called in %s on line %d
Checks\Purse::op_Addition(): Argument #2 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given, called in %s on line %d
Checks\Pages::{closure:Checks\Pages::taker():%d}(): Argument #1 ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given, called in %s on line %d
read
nested
Checks\Pages::read(): Argument #1 ($feed) must be of type Checks.Feed<App.DatabaseEntity>, Checks.Stream<Any?> given, called in %s on line %d
drained
Checks\Pages::drain(): Argument #1 ($sink) must be of type Checks.Sink<App.Order>, Checks.Drain<Checks.Customer> given, called in %s on line %d
bool(true)
bool(true)
bool(false)
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
sized
Checks\Tally::sizes(): Argument #1 ($sizes) must be of type List<int>, array given, called in %s on line %d
Checks\Tally::sizes(): Argument #1 ($sizes) must be of type List<int>, array given, called in %s on line %d
Checks\Tally::sizes(): Argument #2 ($prices) must be of type Map<string, float>, array given, called in %s on line %d
Checks\Tally::sizes(): Argument #3 ($tags) must be of type Set<string>, array given, called in %s on line %d
Checks\Tally::rows(): Argument #1 ($rows) must be of type List<List<int>>, array given, called in %s on line %d
float(1)
int(1)
7
Lib\Pager::sizes(): Return value must be of type List<int>, array returned
ints other prices other
bool(true)
bool(false)
array(1) {
  [0]=>
  int(3)
}
NULL
scalar scalar entry entry other other
bool(true)
bool(false)
bool(true)
bool(false)
bool(false)
string(4) "none"
int(7)
NULL
[2.5]
[1.0,2.5]
Cannot assign array to property Checks\Tally::$amounts of type List<float>
Cannot assign App.Box<Checks.Customer> to property Checks\Rack::$own of type App.Box<App.Order>?
owned
Cannot assign string to property Checks\Cell::$value of type float
float(3)
Cannot assign App.Box<Checks.Customer> to property Checks\Ledger::$kept of type App.Box<App.Order>?
1
Cannot take a reference to `Checks\Rack::$own`: its type depends on the object's type arguments
Cannot take a reference to `Checks\Rack::$own`: its type depends on the object's type arguments
Cannot take a reference to `Checks\Rack::$own`: its type depends on the object's type arguments
Cannot take a reference to `Checks\Rack::$own`: its type depends on the object's type arguments
Cannot take a reference to `Checks\Rack::$own`: its type depends on the object's type arguments
Cannot take a reference to `Checks\Ledger::$first`: its type depends on the object's type arguments
bool(true)
Cannot assign App.Box<Checks.Customer> to property Checks\Rack::$own of type App.Box<App.Order>?
Cannot take a reference to `Checks\Rack::$own`: its type depends on the object's type arguments
Cannot assign array to property Checks\Tally::$amounts of type List<float>
bool(false)
array(3) {
  ["order["lines"][1]"]=>
  string(30) "expected int, got string 'two'"
  ["order["lines"][2]"]=>
  string(%d) "expected int, got string 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx…'"
  ["order["prices"]"]=>
  string(27) "expected List<int>, got Map"
}
bool(true)
array(0) {
}
float(1)
int(1)
bool(false)
array(1) {
  ["counts["vip"]"]=>
  string(34) "key expected int, got string 'vip'"
}
bool(false)
array(2) {
  ["kinds["raw"]"]=>
  string(76) "expected Class<App.PaginatedList<App.Order>>, got string 'App\PaginatedList'"
  ["kinds["none"]"]=>
  string(55) "expected Class<App.PaginatedList<App.Order>>, got int 7"
}
Sharp\Internal\accepts(): Argument #1 ($type) must be one PHP# type
Sharp\Internal\accepts(): Argument #1 ($type) must name only type parameters the calling code has type arguments for
