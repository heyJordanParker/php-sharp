<?php declare(strict_types=1); namespace Demo;

use Lib\Callbacks;
use Lib\Probe;
use OutOfRangeException;

class Lambdas
{
    private int $base = 10;

    public function run(int $extra): int
    {
        $count = 0;
        $twice = fn (int $a) => $a * $extra;
        $half = fn ($b) => \intdiv($b, 2);
        $add = function (int $step) use (&$count) {
            $count += $step + $this->base;
        };
        $bump = function () use (&$count) { return $count += 1; };
        Callbacks::call($add, $twice($half(4)));
        $bump();
        return $count;
    }

    public static function doubled(array $numbers, int $by): array
    {
        return \Sharp\Internal\map($numbers, fn ($n) => $n * $by);
    }

    public static function passes(int $times): string
    {
        $marks = [fn () => 0];
        while ($times > 0) {
            { unset($seen); $seen = $times; }
            $mark = function () use (&$seen) {
                $seen += 100;
            };
            $mark();
            \Sharp\Internal\add($marks, function () use (&$seen) { return $seen; });
            $times -= 1;
        }
        return Probe::join(",", \Sharp\Internal\map($marks, fn ($read) => $read()));
    }

    public static function pipeline(array $numbers, \Sharp\Map|array $prices): array { $prices = \is_array($prices) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [(string) $key, $value], \array_keys($prices), $prices))) : $prices; // PHP# makes the array a Map, its keys strings

        $odd = \Sharp\Internal\filter($numbers, fn ($n) => $n % 2 === 1);
        $labels = \Sharp\Internal\map($odd, fn ($n) => (string) ($n * 10));
        $groups = \Sharp\Internal\groupBy($numbers, fn ($n) => $n % 2 === 0 ? "even" : "odd", "string, List<int>");
        return [
            Probe::join(",", $labels),
            Probe::join(",", \Sharp\Internal\sortedBy($numbers, fn ($n) => -$n, null)),
            Probe::join(",", $groups["odd"] ?? []),
            Probe::join(",", \Sharp\Internal\keys(\Sharp\Internal\associateBy($labels, fn ($label) => $label, "string, string"))),
            Probe::join(",", \Sharp\Internal\keys(\Sharp\Internal\filterEntries($prices, fn ($price) => $price < 100))),
            Probe::join(",", \Sharp\Internal\arrayOf(\Sharp\Internal\filterEntries($prices, fn ($price) => $price < 100))), // PHP# passes plain PHP the Map's array
            Probe::join(",", \Sharp\Internal\map($prices, fn ($price) => $price * 2)),
        ];
    }

    public static function total(array $numbers): int { return \Sharp\Internal\sumOf($numbers, fn ($n) => $n * 2) + \Sharp\Internal\first($numbers, fn ($n) => $n > 2) + (\Sharp\Internal\any($numbers, fn ($n) => $n > 9) ? 1 : 0); }

    public static function failures(array $numbers): array
    {
        $messages = [];
        try {
            \Sharp\Internal\first($numbers, fn ($n) => $n > 100);
        } catch (OutOfRangeException $missing) {
            \Sharp\Internal\add($messages, $missing->getMessage());
        }
        try {
            \Sharp\Internal\map($numbers, fn ($n) => $n > 2 ? throw new OutOfRangeException((string) $n) : $n);
        } catch (OutOfRangeException $stopped) {
            \Sharp\Internal\add($messages, $stopped->getMessage());
        }
        return $messages;
    }

    public static function captured(array $numbers): array
    {
        $seen = $numbers;
        $kept = \Sharp\Internal\filter($seen, function ($n) use (&$seen) {
            \Sharp\Internal\add($seen, $n);
            return true;
        });
        $grown = \count($seen);
        $size = function () use (&$seen) { return \count($seen); };
        \Sharp\Internal\add($seen, 0);
        return [\count($kept), $grown, $size()];
    }

    public function nested(int $extra): int
    {
        $outer = function (int $a) use ($extra) {
            $inner = fn ($b) => $a + $b + $extra + $this->base;
            return $inner(1);
        };
        return $outer(2);
    }

    public static function scaled(array $numbers): array { return \Sharp\Internal\map($numbers, Lambdas::twice(...)); }

    private static function twice(int $n): int { return $n * 2; }
}
