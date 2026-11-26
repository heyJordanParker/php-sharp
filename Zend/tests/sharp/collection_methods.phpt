--TEST--
PHP# collection methods change a List or Map where it lives, and leave objects to their own methods
--FILE--
<?php

namespace {
    require __DIR__ . '/attempt.inc';
    require __DIR__ . '/vendor/autoload.php';

    for ($round = 0; $round < 100; $round++) {
        ob_start();
        attempt(fn () => Demo\Pantry::grow([1, 2]));
        attempt(fn () => Demo\Pantry::drop(['tea' => 3, '5' => 7], 'tea'));
        attempt(fn () => Demo\Pantry::at([1, 2], 1));
        attempt(fn () => Demo\Pantry::at([1, 2], 2));
        attempt(fn () => Demo\Pantry::at([1, 2], -1));
        attempt(fn () => Demo\Pantry::price(['tea' => 3, '5' => 7], '5'));
        attempt(fn () => Demo\Pantry::price(['tea' => 3], 'pie'));
        attempt(fn () => Demo\Pantry::entries([5, 6]));
        attempt(fn () => Demo\Pantry::first([[1, 2]]));
        attempt(fn () => Demo\Pantry::rows([[1, 2]]));
        attempt(fn () => Demo\Pantry::setPast([1, 2]));

        $basket = new Demo\Basket();
        $pantry = new Demo\Pantry($basket);
        attempt(fn () => $pantry->restock('tea'));
        attempt(fn () => $pantry->restock('none'));
        attempt(fn () => Demo\Pantry::stack($pantry, 8));
        attempt(fn () => Demo\Pantry::fill($basket, 3));
        attempt(fn () => Demo\Pantry::fill($basket, 4));
        attempt(fn () => $basket->writes);
        attempt(fn () => Demo\Pantry::total($basket));
        attempt(fn () => Demo\Pantry::size($pantry));
        attempt(fn () => Demo\Pantry::lineCount($basket));
        attempt(fn () => Demo\Pantry::made());
        attempt(fn () => Demo\Pantry::sizeCount([]));
        attempt(fn () => Demo\Pantry::sizeCount([4, 5, 6]));
        attempt(fn () => Demo\Pantry::stockCount(['tea' => 3, '5' => 7]));
        attempt(fn () => $pantry->heldCount());
        attempt(fn () => Demo\Tray::filled());
        attempt(fn () => Demo\Link::after(new Demo\Link(1), 2));
        attempt(fn () => Demo\Link::after(null, 2));
        attempt(fn () => Demo\Link::skipped(new Demo\Link(1))->value);
        attempt(fn () => Demo\Link::skipped(null));
        attempt(fn () => Demo\Link::label(new Demo\Link(1), new ArrayObject([1, 2])));

        // insert takes an index from 0 to the count, and the count appends.
        attempt(fn () => Demo\Pantry::inserted([1, 2], 0, 9));
        attempt(fn () => Demo\Pantry::inserted([1, 2], 2, 9));
        attempt(fn () => Demo\Pantry::inserted([1, 2], 3, 9));
        attempt(fn () => Demo\Pantry::inserted([1, 2], -1, 9));
        // remove takes the first equal element and renumbers; the caller's copy stays as it was.
        $sizes = [1, 2, 1];
        attempt(fn () => Demo\Pantry::removes($sizes, 1));
        attempt(fn () => Demo\Pantry::removes($sizes, 5));
        attempt(fn () => $sizes);
        attempt(fn () => Demo\Pantry::removed([1, 2, 1], 1));
        attempt(fn () => Demo\Pantry::cleared([1, 2]));
        attempt(fn () => Demo\Pantry::reordered());
        // A change to a property with a set hook runs the hook once; a remove that finds nothing runs it never.
        $shelf = new Demo\Basket();
        $shelf->lines = [3, 4];
        attempt(function () use ($shelf) { Demo\Pantry::front($shelf, 1); return [$shelf->lines, $shelf->writes]; });
        attempt(fn () => [Demo\Pantry::take($shelf, 3), $shelf->lines, $shelf->writes]);
        attempt(fn () => [Demo\Pantry::take($shelf, 9), $shelf->lines, $shelf->writes]);
        attempt(function () use ($shelf) { Demo\Pantry::empty($shelf); return [$shelf->lines, $shelf->writes]; });
        attempt(fn () => Demo\Pantry::last([4, 5]));
        attempt(fn () => Demo\Pantry::last([]));
        // max and min are the largest and smallest element, and throw on an empty list as last does.
        attempt(fn () => Demo\Pantry::largest([4, -9, 5]));
        attempt(fn () => Demo\Pantry::smallest([4, -9, 5]));
        attempt(fn () => Demo\Pantry::largest([]));
        attempt(fn () => Demo\Pantry::smallest([]));
        // count runs on a local that may hold the List or the Map.
        attempt(fn () => Demo\Pantry::counted(true, [1, 2, 3], ['tea' => 1]));
        attempt(fn () => Demo\Pantry::counted(false, [1, 2, 3], ['tea' => 1]));
        attempt(fn () => Demo\Pantry::position([4, 5, 5], 5));
        attempt(fn () => Demo\Pantry::position([4, 5], 9));
        attempt(fn () => Demo\Pantry::holds([4, 5], 5));
        attempt(fn () => Demo\Pantry::holds([4, 5], 9));
        // has tells a stored null from a missing key.
        attempt(fn () => Demo\Pantry::has(['pro' => null], 'pro'));
        attempt(fn () => Demo\Pantry::has(['pro' => null], 'team'));
        // PHP stores the key "5" as the int 5, and keys() gives it back as the string "5".
        attempt(fn () => Demo\Pantry::names(['5' => 1, 'tea' => 2]));
        // values() gives the values in the order their keys were stored, and toSet() keeps each element once.
        attempt(fn () => Demo\Pantry::amounts(['tea' => 2, 'pie' => 7, 'cake' => 2]));
        attempt(fn () => Demo\Pantry::unique([3, 1, 3, 2, 1]));
        // A collection keys a Map and joins a Set by its elements: a List in order, a Set in any order.
        attempt(fn () => Demo\Pantry::keyedByCollections());
        attempt(fn () => Demo\Pantry::cheap(['tea' => 3, 'cake' => 300, 'pie' => 7]));
        attempt(fn () => Demo\Pantry::sold(['tea' => 3], 'tea'));
        attempt(fn () => Demo\Pantry::sold(['tea' => 3], 'pie'));
        // A search compares with the element's operator ==, so a new coin of the same value is found.
        attempt(fn () => Demo\Coin::held([new Demo\Coin(5)], 5));
        attempt(fn () => Demo\Coin::spent([new Demo\Coin(5), new Demo\Coin(7)], 5));
        $output = ob_get_clean();
    }

    echo $output;

    // remove, contains and clear on a value that may be a List or another collection run the List's or the
    // collection's own. They run once, after the rounds, so the objects they make leave the numbers above as they are.
    attempt(fn () => Demo\Pantry::shared(true, ['tea', 'pie'], ['tea' => 1, 'pie' => 2], ['cake']));
    attempt(fn () => Demo\Pantry::shared(false, ['tea', 'pie'], ['tea' => 1, 'pie' => 2], ['cake']));

    // A call that changes a property lets go of the object when it returns.
    $basket = new Demo\Basket();
    Demo\Pantry::fill($basket, 5);
    $kept = WeakReference::create($basket);
    unset($basket);
    var_dump($kept->get());

    // So does one whose call never ran because an argument threw.
    $basket = new Demo\Basket();
    $kept = WeakReference::create($basket);
    attempt(fn () => Demo\Pantry::share($basket, 5, 0));
    unset($basket);
    var_dump($kept->get());
}
?>
--EXPECT--
array(3) {
  [0]=>
  int(9)
  [1]=>
  int(2)
  [2]=>
  int(4)
}
array(1) {
  [5]=>
  int(7)
}
int(2)
NULL
NULL
int(7)
NULL
array(2) {
  [0]=>
  int(5)
  [1]=>
  int(6)
}
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
array(1) {
  [0]=>
  array(2) {
    [0]=>
    int(1)
    [1]=>
    int(2)
  }
}
OutOfRangeException: Undefined array key 2 in Pantry.sharp on line 55
array(1) {
  ["pie"]=>
  int(7)
}
array(1) {
  ["pie"]=>
  int(7)
}
array(3) {
  [0]=>
  int(0)
  [1]=>
  int(1)
  [2]=>
  int(8)
}
array(1) {
  [0]=>
  int(3)
}
array(2) {
  [0]=>
  int(3)
  [1]=>
  int(4)
}
int(2)
int(7)
int(2)
int(2)
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
int(0)
int(3)
int(2)
int(3)
array(2) {
  [0]=>
  int(5)
  [1]=>
  int(6)
}
int(3)
NULL
int(4)
NULL
string(6) "2 of 2"
array(3) {
  [0]=>
  int(9)
  [1]=>
  int(1)
  [2]=>
  int(2)
}
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(9)
}
OutOfRangeException: Undefined array key 3 in Pantry.sharp on line 123
OutOfRangeException: Undefined array key -1 in Pantry.sharp on line 123
bool(true)
bool(false)
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(1)
}
array(2) {
  [0]=>
  int(2)
  [1]=>
  int(1)
}
array(0) {
}
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(9)
  [2]=>
  int(3)
}
array(2) {
  [0]=>
  array(3) {
    [0]=>
    int(1)
    [1]=>
    int(3)
    [2]=>
    int(4)
  }
  [1]=>
  int(2)
}
array(3) {
  [0]=>
  bool(true)
  [1]=>
  array(2) {
    [0]=>
    int(1)
    [1]=>
    int(4)
  }
  [2]=>
  int(3)
}
array(3) {
  [0]=>
  bool(false)
  [1]=>
  array(2) {
    [0]=>
    int(1)
    [1]=>
    int(4)
  }
  [2]=>
  int(3)
}
array(2) {
  [0]=>
  array(0) {
  }
  [1]=>
  int(4)
}
int(5)
OutOfRangeException: The list is empty. in Pantry.sharp on line 172
int(5)
int(-9)
OutOfRangeException: The list is empty. in Pantry.sharp on line 175
OutOfRangeException: The list is empty. in Pantry.sharp on line 177
int(3)
int(1)
int(1)
NULL
bool(true)
bool(false)
bool(true)
bool(false)
array(2) {
  [0]=>
  string(1) "5"
  [1]=>
  string(3) "tea"
}
array(3) {
  [0]=>
  int(2)
  [1]=>
  int(7)
  [2]=>
  int(2)
}
array(3) {
  [0]=>
  int(3)
  [1]=>
  int(1)
  [2]=>
  int(2)
}
array(4) {
  [0]=>
  int(2)
  [1]=>
  string(1) "b"
  [2]=>
  string(4) "none"
  [3]=>
  int(1)
}
array(2) {
  ["tea"]=>
  int(3)
  ["pie"]=>
  int(7)
}
bool(true)
bool(false)
bool(true)
array(1) {
  [0]=>
  int(7)
}
string(13) "true 1 true 0"
string(14) "true 1 false 0"
NULL
DivisionByZeroError: Modulo by zero in Pantry.sharp on line 80
NULL
