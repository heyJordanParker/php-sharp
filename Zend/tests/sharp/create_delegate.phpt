--TEST--
Method.createDelegate makes a value of a function type that calls the method, an instance one on the receiver it takes first, of its class or one around it such as Object, and a computed property's through getter(), and refuses a method whose parameters, result or effects don't fit the type: a PHP# method's are those its compile solved, one whose result alone it carries may have every effect even when that result is a function type, and a plain PHP method's are those its extern lines declare, or every effect without one
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use App\Delegates\Database;
use App\Delegates\Mailer;
use App\Delegates\Order;
use App\Delegates\Rush;
use App\Delegates\Walk;

function attempt(string $label, Closure $body): void
{
    echo $label, ": ";
    try {
        var_export($body());
        echo "\n";
    } catch (Throwable $error) {
        echo get_class($error), ": ", $error->getMessage(), "\n";
    }
}

$order = new Order(new Database(), new Mailer());

attempt('rows', fn () => Walk::rows()($order));
attempt('rows is its type', fn () => Walk::rowsIsItsType());
attempt('count of a rush on a rush', fn () => Walk::rushCount()(new Rush(new Database(), new Mailer())));
attempt('count of a rush on an order', fn () => Walk::rushCount()($order));
attempt('add', fn () => Walk::add()($order, 2));
attempt('add "2"', fn () => Walk::add()($order, '2'));
attempt('unit', fn () => Walk::unit()());
attempt('count on an order', fn () => Walk::count()($order));
attempt('count on a rush', fn () => Walk::count()(new Rush(new Database(), new Mailer())));
attempt('doubled', fn () => Walk::doubled()($order));
attempt('total 5', fn () => Walk::total()($order, 5));
attempt('doubled after', fn () => Walk::doubled()($order));
attempt('total "6"', fn () => Walk::total()($order, '6'));
attempt('total with no value', fn () => Walk::total()($order));
attempt('stock', fn () => Walk::stock()());
attempt('rows on another class', fn () => Walk::rows()(new stdClass()));
attempt('rows with no receiver', fn () => Walk::rows()());

attempt('wrong parameter', fn () => Walk::wrongParameter());
attempt('wrong result', fn () => Walk::wrongResult());
attempt('effect not allowed', fn () => Walk::effectNotAllowed());
attempt('no receiver', fn () => Walk::noReceiver());
attempt('other receiver', fn () => Walk::otherReceiver());
attempt('too few', fn () => Walk::tooFew());
attempt('no function', fn () => Walk::noFunction());
attempt('plain PHP', fn () => Walk::plainPhp());
attempt('rows of any object', fn () => Walk::rowsOfAnyObject()($order));
attempt('rows of any object on another class', fn () => Walk::rowsOfAnyObject()(new stdClass()));
attempt('itself', fn () => Walk::itself()($order) === $order);
attempt('a function result', fn () => Walk::functionResult());
attempt('the function a function result gives', fn () => $order->counter()());
attempt('a closure of a function result is its result', fn () => Walk::isCounter($order->counter(...)));
attempt('the function a function result gives is its type', fn () => Walk::isCounter($order->counter()));
attempt('declared pure', fn () => Walk::declaredPure()(new RuntimeException('boom')));
attempt('declared effect', fn () => Walk::declaredEffect());
attempt('declared effect allowed', fn () => Walk::declaredEffectAllowed()(new Sharp\Psr\Container(), 'mailer'));
?>
--EXPECT--
rows: 3
rows is its type: true
count of a rush on a rush: 2
count of a rush on an order: TypeError: `App.Delegates.Rush.count` takes a receiver of `App\Delegates\Rush`, and was given App\Delegates\Order.
add: 6
add "2": TypeError: App\Delegates\Order::add(): Argument #1 ($amount) must be of type int, string given
unit: 1
count on an order: 1
count on a rush: ValueError: `App.Delegates.Rush.count` has the effect `App.Delegates.Mailer`, and `Function<int(App.Delegates.Order)>` allows none.
doubled: 8
total 5: NULL
doubled after: 10
total "6": TypeError: App\Delegates\Order::$total::set(): Argument #1 ($value) must be of type int, string given
total with no value: ArgumentCountError: `App.Delegates.Order.total.set` takes 1 argument, and was given 0.
stock: 5
rows on another class: TypeError: `App.Delegates.Order.rows` takes a receiver of `App\Delegates\Order`, and was given stdClass.
rows with no receiver: TypeError: `App.Delegates.Order.rows` takes a receiver of `App\Delegates\Order`, and was given none.
wrong parameter: ValueError: `App.Delegates.Order.add` takes `int` as `amount`, and `Function<int(App.Delegates.Order, string)>` passes `string`.
wrong result: ValueError: `App.Delegates.Order.add` returns `int`, and `Function<string(App.Delegates.Order, int)>` returns `string`.
effect not allowed: ValueError: `App.Delegates.Order.notify` has the effect `App.Delegates.Mailer`, and `Function<int(App.Delegates.Order) uses App.Delegates.Database>` allows only `App.Delegates.Database`.
no receiver: ValueError: `App.Delegates.Order.add` takes a receiver of `App.Delegates.Order` first, and `Function<int()>` passes nothing.
other receiver: ValueError: `App.Delegates.Order.add` takes a receiver of `App.Delegates.Order` first, and `Function<int(string, int)>` passes `string`.
too few: ValueError: `App.Delegates.Order.add` takes 1 argument, and `Function<int(App.Delegates.Order)>` passes 0.
no function: ValueError: `createDelegate` makes a value of a function type, and was given `int`.
plain PHP: ValueError: `Psr.Container.ContainerInterface.get` may have every effect, and `Function<Any?(Psr.Container.ContainerInterface, string)>` allows none.
rows of any object: 3
rows of any object on another class: TypeError: `App.Delegates.Order.rows` takes a receiver of `App\Delegates\Order`, and was given stdClass.
itself: true
a function result: ValueError: `App.Delegates.Order.counter` may have every effect, and `Function<Function<int()>(App.Delegates.Order)>` allows none.
the function a function result gives: 1
a closure of a function result is its result: false
the function a function result gives is its type: true
declared pure: 'boom'
declared effect: ValueError: `Sharp.Psr.Container.has` has the effect `App.Delegates.Database`, and `Function<bool(Sharp.Psr.Container, string)>` allows none.
declared effect allowed: false
