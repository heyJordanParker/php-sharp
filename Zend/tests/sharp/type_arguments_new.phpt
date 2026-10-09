--TEST--
new of a generic PHP# class gives the object its type arguments before its constructor runs, clone copies them, and an object plain PHP creates has its class's bounds
--FILE--
<?php
require __DIR__ . '/type_arguments.inc';

use App\Queue;

echo "new\n";
var_dump(arguments(Queue::orders()));
var_dump(arguments(Queue::pair()));
// new Self gives the new object the type arguments of this.
var_dump(arguments(Queue::orders()->copy()));
var_dump(arguments(Queue::entities()->copy()));
// OrderPage and Order declare no type parameter.
var_dump(arguments(Queue::page()));
var_dump(arguments(new App\Order(1)));
var_dump(arguments(new stdClass()));

echo "an object plain PHP creates\n";
var_dump(arguments(new App\PaginatedList([])));
var_dump(arguments(new App\Pair(1, 2)));
// The bounds are the type arguments PHP# writes as PaginatedList<DatabaseEntity>, and no others.
var_dump(new App\PaginatedList([]) == Queue::entities());
var_dump(new App\PaginatedList([]) == Queue::orders());

echo "clone\n";
$pair = Queue::pair();
$copy = clone $pair;
unset($pair);
var_dump(arguments($copy));
var_dump(arguments(clone new App\PaginatedList([])));

echo "new Self in a lambda\n";
$copy = Queue::orders()->copyLater();
var_dump($copy::class, arguments($copy));
// The lambda reads this's type arguments, so it uses this, and PHP refuses to unbind it.
$maker = Closure::bind(Queue::orders()->maker(), null, App\Order::class);
var_dump($maker === null ? null : $maker());
// A lambda around it does not use this, as in PHP, so it unbinds, and the inner lambda throws as $this does.
foreach ([Queue::orders()->makerLater(), Queue::orders()->emptiedLater()] as $later) {
    try {
        var_dump(Closure::bind($later, null, App\Order::class)()());
    } catch (Error $error) {
        echo $error::class, ': ', $error->getMessage(), "\n";
    }
}

echo "new with a type argument that names a type parameter of the class\n";
// It takes this's type argument, nested, nullable or in a union, as code would write the type it is.
echo implode(', ', arguments(Queue::orders()->paired(new App\Order(5)))), "\n";
echo implode(', ', arguments(Queue::entities()->paired(new App\Order(5)))), "\n";
echo implode(', ', arguments(Queue::pair()->swapped())), "\n";
echo implode(', ', arguments(Queue::pair()->swapped()->swapped())), "\n";
echo implode(', ', arguments(Queue::pair()->widened())), "\n";
echo implode(', ', arguments(Queue::pair()->widened()->swapped()->swapped()->widened())), "\n";
// One the code spells the same way is the same type.
var_dump(Queue::orders()->emptied()() == new App\PaginatedList([]), Queue::orders()->emptied()() == Queue::orders());
// The lambda reads this's type arguments, so it uses this.
var_dump(Closure::bind(Queue::orders()->emptied(), null, App\Order::class));
// A type argument the request read from input stays the request's.
$read = unserialize(str_replace('s:9:"App.Order"', 's:15:"App.SharedOrder"', serialize(Queue::orders())));
echo implode(', ', arguments($read->paired(new App\SharedOrder(6)))), "\n";
// In a method a subclass inherits, the subclass's header gives the method's class its type arguments.
echo implode(', ', arguments(Queue::orderMaker()->make())), "\n";
echo implode(', ', arguments(Queue::listMaker()->make())), "\n";

echo "lazy objects\n";
$class = new ReflectionClass(App\PaginatedList::class);
$orders = static fn (): App\PaginatedList => Queue::orders();
$construct = static function (App\PaginatedList $ghost): void {
    $ghost->__construct([]);
};
// A proxy reads the type arguments of its real instance, which it initializes first.
var_dump(arguments($class->newLazyProxy($orders)));
var_dump($class->newLazyProxy($orders) == Queue::orders());
show(serialize($class->newLazyProxy($orders)));
var_dump(arguments($class->newLazyProxy($orders)->copy()));
// A proxy whose initializer throws hands that exception on, and makes nothing.
$failing = $class->newLazyProxy(static function (): never {
    throw new RuntimeException('no orders');
});
foreach ([
    static fn () => arguments($failing),
    static fn () => $failing == Queue::orders(),
    static fn () => serialize($failing),
    static fn () => $failing->copy(),
] as $read) {
    try {
        var_dump($read());
    } catch (RuntimeException $exception) {
        echo $exception->getMessage(), "\n";
    }
}
// A ghost's type arguments are never lazy, so reading them leaves it lazy.
$ghost = $class->newLazyGhost($construct);
var_dump(arguments($ghost), $class->isUninitializedLazyObject($ghost));
var_dump($ghost == Queue::entities(), $class->isUninitializedLazyObject($ghost));
// Resetting an object keeps its type arguments.
$reset = Queue::orders();
$class->resetAsLazyGhost($reset, $construct);
var_dump(arguments($reset));
$reset = Queue::orders();
$class->resetAsLazyProxy($reset, $orders);
var_dump(arguments($reset));
// Setting every visible property initializes a ghost, which never runs its initializer.
$ghost = $class->newLazyGhost(static function (): void {
    echo "the initializer ran\n";
});
$class->getProperty('items')->setRawValueWithoutLazyInitialization($ghost, []);
var_dump($class->isUninitializedLazyObject($ghost));

echo "the constructor\n";
// PaginatedList's constructor hands this to Lib\Witness::see before it returns.
Lib\Witness::$look = static function (object $object): void {
    echo get_class($object), ': ', implode(', ', arguments($object)), "\n";
};
Queue::orders();
Queue::entities()->copy();
Queue::page();
Lib\Witness::$look = null;
?>
--EXPECTF--
new
array(1) {
  [0]=>
  string(9) "App.Order"
}
array(2) {
  [0]=>
  string(9) "App.Order"
  [1]=>
  string(3) "int"
}
array(1) {
  [0]=>
  string(9) "App.Order"
}
array(1) {
  [0]=>
  string(18) "App.DatabaseEntity"
}
array(0) {
}
array(0) {
}
array(0) {
}
an object plain PHP creates
array(1) {
  [0]=>
  string(18) "App.DatabaseEntity"
}
array(2) {
  [0]=>
  string(4) "Any?"
  [1]=>
  string(4) "Any?"
}
bool(true)
bool(false)
clone
array(2) {
  [0]=>
  string(9) "App.Order"
  [1]=>
  string(3) "int"
}
array(1) {
  [0]=>
  string(18) "App.DatabaseEntity"
}
new Self in a lambda
string(17) "App\PaginatedList"
array(1) {
  [0]=>
  string(9) "App.Order"
}

Warning: Cannot unbind $this of closure using $this, this will be an error in PHP 9 in %s on line %d
NULL
Error: Using $this when not in object context
Error: Using $this when not in object context
new with a type argument that names a type parameter of the class
App.Order, List<App.Order>?
App.DatabaseEntity, List<App.DatabaseEntity>?
int, App.Order?
App.Order?, int?
App.Order|int, int
(App.Order|int)?, int?
bool(false)
bool(true)

Warning: Cannot unbind $this of closure using $this, this will be an error in PHP 9 in %s on line %d
NULL
App.SharedOrder, List<App.SharedOrder>?
App.Order
List<int>
lazy objects
array(1) {
  [0]=>
  string(9) "App.Order"
}
bool(true)
O:17:"App\PaginatedList":2:{s:5:"items";a:0:{}s:14:"\0<sharp>\0types";s:9:"App.Order";}
array(1) {
  [0]=>
  string(9) "App.Order"
}
no orders
no orders
no orders
no orders
array(1) {
  [0]=>
  string(18) "App.DatabaseEntity"
}
bool(true)
bool(true)
bool(false)
array(1) {
  [0]=>
  string(9) "App.Order"
}
array(1) {
  [0]=>
  string(9) "App.Order"
}
bool(false)
the constructor
App\PaginatedList: App.Order
App\PaginatedList: App.DatabaseEntity
App\PaginatedList: App.DatabaseEntity
App\OrderPage:
