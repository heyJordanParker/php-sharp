<?php

namespace Lib {
    final class Basket
    {
        public int $writes = 0;

        /** @var list<int> */
        public array $lines = [] {
            set(array $value) {
                $this->writes++;
                $this->lines = $value;
            }
        }

        /** @var list<int> */
        public array $totals {
            get => [array_sum($this->lines)];
        }

        public function size(): int
        {
            return count($this->lines);
        }
    }
}

namespace {
    require __DIR__ . '/attempt.inc';
    require __DIR__ . '/CollectionMethods.sharp';

    for ($round = 0; $round < 100; $round++) {
        ob_start();
        attempt(fn () => Demo\Shelf::grow([1, 2]));
        attempt(fn () => Demo\Shelf::drop(['tea' => 3, '5' => 7], 'tea'));
        attempt(fn () => Demo\Shelf::at([1, 2], 1));
        attempt(fn () => Demo\Shelf::at([1, 2], 2));
        attempt(fn () => Demo\Shelf::price(['tea' => 3, '5' => 7], '5'));
        attempt(fn () => Demo\Shelf::price(['tea' => 3], 'pie'));
        attempt(fn () => Demo\Shelf::entries([5, 6]));
        attempt(fn () => Demo\Shelf::first([[1, 2]]));
        attempt(fn () => Demo\Shelf::rows([[1, 2]]));
        attempt(fn () => Demo\Shelf::setPast([1, 2]));

        $basket = new Lib\Basket();
        $shelf = new Demo\Shelf($basket);
        attempt(fn () => $shelf->restock('tea'));
        attempt(fn () => $shelf->restock('none'));
        attempt(fn () => Demo\Shelf::stack($shelf, 8));
        attempt(fn () => Demo\Shelf::fill($basket, 3));
        attempt(fn () => Demo\Shelf::fill($basket, 4));
        attempt(fn () => $basket->writes);
        attempt(fn () => Demo\Shelf::total($basket));
        attempt(fn () => Demo\Shelf::size($shelf));
        attempt(fn () => Demo\Shelf::lineCount($basket));
        attempt(fn () => Demo\Shelf::made());
        attempt(fn () => Demo\Tray::filled());
        attempt(fn () => Demo\Link::after(new Demo\Link(1), 2));
        attempt(fn () => Demo\Link::after(null, 2));
        attempt(fn () => Demo\Link::further(new Demo\Link(1))->value);
        attempt(fn () => Demo\Link::further(null));
        attempt(fn () => Demo\Link::label(new Demo\Link(1), new ArrayObject([1, 2])));
        $output = ob_get_clean();
    }

    echo $output;

    // A collection kept for reuse lets go of the object whose property it changed when its call returns.
    $basket = new Lib\Basket();
    Demo\Shelf::fill($basket, 5);
    $kept = WeakReference::create($basket);
    unset($basket);
    var_dump($kept->get());

    // So does one whose call never ran because an argument threw.
    $basket = new Lib\Basket();
    $kept = WeakReference::create($basket);
    attempt(fn () => Demo\Shelf::share($basket, 5, 0));
    unset($basket);
    var_dump($kept->get());

    // The wrapper exists only as the receiver of one call: PHP code cannot make, copy or keep one.
    attempt(fn () => new Sharp\Collection());
    attempt(fn () => (new ReflectionClass(Sharp\Collection::class))->newInstanceWithoutConstructor());
}
