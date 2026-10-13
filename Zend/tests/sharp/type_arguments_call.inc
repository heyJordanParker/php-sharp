<?php
require __DIR__ . '/type_arguments_calls.inc';
require __DIR__ . '/harness/Inspector.inc';

use App\Box;
use App\Order;
use App\SharedOrder;
use Calls\Counter;
use Calls\OrderPicker;
use Calls\Repository;
use Calls\SharpPlainWrapper;
use Calls\SharpWrapper;
use Calls\Shelf;

// A hot function's second call runs the code the tracing JIT compiled on its first. Calls\Repository::suspended
// suspends the Fiber after it sent the first argument of a generic call.
$order = new Order(1);
foreach ([1, 2] as $round) {
    echo shown(Repository::written($order)), "\n";
    echo shown(Repository::inferred($order)), "\n";
    echo shown((new Shelf())->stock($order)), "\n";
    echo shown((new Shelf())->made($order)), "\n";
    echo shown(Repository::boxedByValue($order)), "\n";
    echo implode(', ', (new Shelf())->taken([Shelf::orders(), Shelf::orders()])), "\n";
    echo shown(Repository::forwarded($order)), "\n";
    echo shown(Repository::spread([$order, new Order(2)])), "\n";
    echo shown(Repository::keyed($order)), "\n";
    echo shown(Repository::numbered()->twice()), "\n";
    echo shown(Repository::wrapped(new SharpWrapper(), $order)), "\n";
    echo shown(Repository::wrappedByParent(new SharpPlainWrapper(), $order)), "\n";
    echo shown((new OrderPicker())->pickOrder($order)), "\n";
    $fiber = new Fiber(fn () => Repository::suspended(new Order($round)));
    $fiber->start();
    echo $fiber->isSuspended() ? 'suspended' : 'returned', "\n";
    $fiber->resume();
    echo shown($fiber->getReturn()), "\n";

    // The methods read the type arguments they run with: Calls\Inspection<App.Order>'s, and Repository's generic calls'.
    $inspection = Repository::orderInspection();
    var_dump($inspection->holds(new Order(1)), $inspection->holds(new SharedOrder(1)));
    var_dump($inspection->kept(new SharedOrder(1)), $inspection->kept(new Order(4))->id);
    // A Box plain PHP creates holds Box's bound, so it is no Box<App.Order>.
    echo $inspection->arm(new Order(1)), ', ', $inspection->arm(new Box()), ', ', $inspection->arm(Repository::written($order)), "\n";
    echo $inspection->kind(), ', ', $inspection->limit(), ', ', $inspection->made(5)::class, ' ', $inspection->made(5)->id, "\n";
    var_dump(Repository::orderBoxed(Repository::written($order)), Repository::orderBoxed(new Box()));
    echo Repository::freshOrder(6)::class, ', ', Repository::orderLimit(), "\n";
    // The clauses test the caught value in order, and one that none matches is thrown on.
    echo Repository::caughtUnexpected(Repository::numberFailure()), "\n";
    echo Repository::caughtUnexpected(Repository::textFailure()), "\n";
    echo Repository::caughtUnexpected(new UnexpectedValueException('four')), "\n";
    try {
        Repository::caughtUnexpected(Repository::flagFailure());
    } catch (RuntimeException $rethrown) {
        echo 'rethrown: ', $rethrown->getMessage(), "\n";
    }
    // A lambda bound to an object of another class has no type arguments of its class to read.
    foreach ([$inspection->tester(), $inspection->namer(), $inspection->maker()] as $lambda) {
        try {
            Closure::bind($lambda, new Order(1), Order::class)(new Order(1));
        } catch (Error $error) {
            echo $error->getMessage(), "\n";
        }
    }
    // Each round writes the statics of type arguments no code used yet, so each first read copies the defaults.
    echo implode(', ', $round === 1 ? Repository::orderStatics($order) : Repository::shelfStatics(new Shelf())), "\n";
    echo implode(', ', Repository::inherited($order)), "\n";
    // The PHP a static field lowers to, written with `[]=` and a nested index, which PHP# writes with other forms.
    $key = $round === 1 ? 'App.Order' : 'Calls.Shelf';
    \Sharp\Internal\statics(Counter::class, $key)['items'][] = $order;
    \Sharp\Internal\statics(Counter::class, $key)['grid'][0][1] = 7;
    echo count(\Sharp\Internal\statics(Counter::class, $key)['items']), ', ',
        \Sharp\Internal\statics(Counter::class, $key)['grid'][0][1], ', ',
        \Sharp\Internal\statics(Counter::class, "List<$key>")['grid'][0][1], "\n";
    // Plain PHP reads a class value as the class name, and the value keeps its type arguments as it passes through, so
    // `new` creates the class with them, and type arguments `new` names win. A name plain PHP writes carries none, so it
    // stands for its class's bounds, as an object plain PHP creates does, and only a class whose header fixes them
    // enters as a `Class<PaginatedList<Order>>`.
    $pages = Repository::orderPages();
    var_dump($pages, $pages === 'App\PaginatedList', $pages === Repository::sharedPages());
    echo shown(Repository::paged($pages)), ', ', shown(Repository::sharedPaged()), ', ',
        shown(Repository::orderPaged(Repository::sharedPages())), "\n";
    echo shown($inspection->listed()), ', ', $inspection->remade(8)::class, ' ', $inspection->remade(8)->id, ', ',
        Repository::sourceCounted(), "\n";
    echo implode(', ', Repository::orderWrittenThrough(new Order(3))), "\n";
    foreach (['App\OrderPage', [Repository::sharedPages()][0], 'App\PaginatedList', 'App\Missing', 7] as $type) {
        try {
            echo shown(Repository::paged($type)), "\n";
        } catch (Error $error) {
            echo $error->getMessage(), "\n";
        }
    }
    $shelf = new Shelf();
    foreach ([$pages, Repository::sharedPages()] as $type) {
        try {
            $shelf->pages = $type;
            echo 'written: ', $shelf->pages === $pages ? 'orderPages' : 'other', "\n";
        } catch (TypeError $error) {
            echo $error->getMessage(), "\n";
        }
    }
}
