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
--EXPECT--
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
