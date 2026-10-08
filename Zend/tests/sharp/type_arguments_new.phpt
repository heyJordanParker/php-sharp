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
the constructor
App\PaginatedList: App.Order
App\PaginatedList: App.DatabaseEntity
App\PaginatedList: App.DatabaseEntity
App\OrderPage:
