<?php

namespace {
    require __DIR__ . '/attempt.inc';

    final class Row
    {
        public function __construct(public bool $paid, public string $number)
        {
        }
    }

    final class Reads
    {
        public int $count = 0;
    }

    function rows(int $count, int $paidEvery, Reads $reads): Generator
    {
        for ($number = 1; $number <= $count; $number++) {
            $reads->count++;
            yield new Row($number % $paidEvery === 0, "n$number");
        }
    }

    function numbers(int $last): Generator
    {
        for ($number = 1; $number <= $last; $number++) {
            echo "yield $number\n";
            yield "n$number" => $number;
        }
    }

    function failing(): Generator
    {
        for ($number = 1; $number <= 5; $number++) {
            yield $number;
        }
        throw new RuntimeException('The source failed after 5 rows');
    }

    function delegate(Sharp\Sequence $sequence): Generator
    {
        yield from $sequence;
    }

    final class Cursor implements IteratorAggregate
    {
        public function getIterator(): Iterator
        {
            return new ArrayIterator(['x' => 7, 'y' => 8, 'z' => 9]);
        }
    }

    function listed(array $list): string
    {
        return json_encode(array_keys($list)) . ' => ' . json_encode(array_values($list));
    }

    for ($round = 0; $round < 100; $round++) {
        ob_start();

        foreach ([1, 2] as $paidEvery) {
            $reads = new Reads();
            $chain = \Sharp\Sequence::from(rows(10000, $paidEvery, $reads))
                ->filter(fn ($o) => $o->paid)
                ->map(fn ($o) => $o->number)
                ->take(100);
            echo "built, $reads->count rows read\n";
            $numbers = $chain->toList();
            echo count($numbers), " numbers from $reads->count of 10000 rows, ", $numbers[0], " to ", $numbers[99], "\n";
        }

        $broken = \Sharp\Sequence::from(failing())->map(fn (int $number): int => $number * 10);
        echo "built a chain over a failing source\n";
        attempt(fn () => $broken->toList());
        attempt(function (): void {
            foreach (\Sharp\Sequence::from(failing())->filter(fn (int $number): bool => true) as $value) {
                echo "$value\n";
            }
        });
        attempt(fn () => listed(\Sharp\Sequence::from(failing())->take(5)->toList()));

        $chain = \Sharp\Sequence::from(numbers(10))
            ->filter(function (int $number): bool {
                echo "filter $number\n";
                return $number % 2 === 0;
            })
            ->map(function (int $number): int {
                echo "map $number\n";
                return $number * 10;
            })
            ->take(3);
        echo "built\n";
        attempt(fn () => listed($chain->toList()));

        attempt(fn () => listed(\Sharp\Sequence::from([5 => 1, 'a' => 2, 7 => 3, 'b' => 4])->toList()));
        attempt(fn () => listed(\Sharp\Sequence::from([5 => 1, 'a' => 2, 7 => 3, 'b' => 4])
            ->filter(fn (int $number): bool => $number > 1)
            ->map(fn (int $number): int => $number * 2)
            ->take(2)
            ->toList()));
        attempt(fn () => listed(\Sharp\Sequence::from((function (): Generator {
            yield 'a' => 1;
            yield 'k' => 2;
            yield 'k' => 3;
            yield 9 => 4;
        })())->toList()));
        attempt(fn () => listed(\Sharp\Sequence::from(new Cursor())->filter(fn (int $number): bool => $number > 7)->toList()));
        $again = \Sharp\Sequence::from(['x' => 1, 'y' => 2]);
        attempt(fn () => listed($again->toList()));
        attempt(fn () => listed($again->toList()));

        foreach (\Sharp\Sequence::from(numbers(5))->map(fn (int $number): int => $number * 2)->take(3) as $key => $value) {
            echo "$key => $value\n";
        }
        $tens = \Sharp\Sequence::from([1, 2, 3])->map(fn (int $number): int => $number * 10);
        foreach ($tens as $outer) {
            $inner = [];
            foreach ($tens as $value) {
                $inner[] = $value;
            }
            echo "$outer: ", implode(',', $inner), "\n";
        }
        foreach (delegate(\Sharp\Sequence::from(numbers(5))->take(3)) as $key => $value) {
            echo "$key => $value\n";
        }
        attempt(fn () => listed(iterator_to_array(\Sharp\Sequence::from(['a' => 1, 'b' => 2])->getIterator())));
        attempt(fn () => get_class(\Sharp\Sequence::from([])->getIterator()));

        $even = \Sharp\Sequence::from([1, 2, 3, 4])->filter(fn (int $number): bool => $number % 2 === 0);
        var_dump(\Sharp\Sequence::from($even) === $even);
        attempt(fn () => listed(\Sharp\Sequence::from($even)->map(fn (int $number): int => $number * 10)->toList()));

        attempt(fn () => listed(\Sharp\Sequence::from(numbers(3))
            ->map(function (int $number): int {
                echo "map $number\n";
                return $number;
            })
            ->take(0)
            ->toList()));
        attempt(fn () => \Sharp\Sequence::from([1])->take(-1));

        attempt(fn () => \Sharp\Sequence::from([1, 2])
            ->filter(fn (int $number): bool => throw new LogicException("filter $number"))
            ->toList());
        attempt(fn () => \Sharp\Sequence::from([1, 2])
            ->map(fn (int $number): int => throw new LogicException("map $number"))
            ->toList());

        $once = \Sharp\Sequence::from(numbers(2))->map(fn (int $number): int => $number);
        attempt(fn () => listed($once->toList()));
        attempt(fn () => listed($once->toList()));

        attempt(fn () => new Sharp\Sequence());
        attempt(fn () => (new ReflectionClass(Sharp\Sequence::class))->newInstanceWithoutConstructor());
        attempt(fn () => clone \Sharp\Sequence::from([]));
        attempt(fn () => serialize(\Sharp\Sequence::from([])));
        attempt(fn () => unserialize('O:14:"Sharp\Sequence":0:{}'));
        attempt(function (): void {
            \Sharp\Sequence::from([])->extra = 1;
        });
        attempt(fn () => \Sharp\Sequence::from([])->filter('is_int'));

        $output = ob_get_clean();
    }

    echo $output;

    $sequence = \Sharp\Sequence::from([1, 2]);
    $sequence = $sequence->filter(function (int $number) use (&$sequence): bool {
        return $sequence !== null;
    });
    $kept = WeakReference::create($sequence);
    unset($sequence);
    var_dump($kept->get() instanceof Sharp\Sequence);
    gc_collect_cycles();
    var_dump($kept->get());

    $generator = delegate(\Sharp\Sequence::from([1, 2])->map(function (int $number) use (&$generator): int {
        return $number;
    }));
    $generator->current();
    $kept = WeakReference::create($generator);
    unset($generator);
    var_dump($kept->get() instanceof Generator);
    gc_collect_cycles();
    var_dump($kept->get());
}
