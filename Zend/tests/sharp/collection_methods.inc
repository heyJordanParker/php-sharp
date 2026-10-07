<?php

namespace {
    require __DIR__ . '/attempt.inc';
    require __DIR__ . '/harness/Basket.inc';
    require __DIR__ . '/CollectionMethods.sharp';

    for ($round = 0; $round < 100; $round++) {
        ob_start();
        attempt(fn () => Demo\Pantry::grow([1, 2]));
        attempt(fn () => Demo\Pantry::drop(['tea' => 3, '5' => 7], 'tea'));
        attempt(fn () => Demo\Pantry::at([1, 2], 1));
        attempt(fn () => Demo\Pantry::at([1, 2], 2));
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
        attempt(fn () => Demo\Tray::filled());
        attempt(fn () => Demo\Link::after(new Demo\Link(1), 2));
        attempt(fn () => Demo\Link::after(null, 2));
        attempt(fn () => Demo\Link::further(new Demo\Link(1))->value);
        attempt(fn () => Demo\Link::further(null));
        attempt(fn () => Demo\Link::skipped(new Demo\Link(1))->value);
        attempt(fn () => Demo\Link::skipped(null));
        attempt(fn () => Demo\Link::label(new Demo\Link(1), new ArrayObject([1, 2])));
        $output = ob_get_clean();
    }

    echo $output;

    // A collection kept for reuse lets go of the object whose property it changed when its call returns.
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

    // The wrapper exists only as the receiver of one call: PHP code cannot make, copy or keep one.
    attempt(fn () => new Sharp\Collection());
    attempt(fn () => (new ReflectionClass(Sharp\Collection::class))->newInstanceWithoutConstructor());
}
