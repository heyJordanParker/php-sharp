<?php

namespace {
    require __DIR__ . '/attempt.inc';
    require __DIR__ . '/harness/Basket.inc';
    require __DIR__ . '/harness/fixture.inc';
    fixture('CollectionMethods');

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

        $basket = new Lib\Basket();
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
        $shelf = new Lib\Basket();
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
    $basket = new Lib\Basket();
    Demo\Pantry::fill($basket, 5);
    $kept = WeakReference::create($basket);
    unset($basket);
    var_dump($kept->get());

    // So does one whose call never ran because an argument threw.
    $basket = new Lib\Basket();
    $kept = WeakReference::create($basket);
    attempt(fn () => Demo\Pantry::share($basket, 5, 0));
    unset($basket);
    var_dump($kept->get());
}
