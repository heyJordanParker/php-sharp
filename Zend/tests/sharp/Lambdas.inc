<?php declare(strict_types=1); namespace Demo;

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
        $add($twice($half(4)));
        $bump();
        return $count;
    }

    public static function doubled(array $numbers, int $by): array
    {
        return \array_map(fn ($n) => $n * $by, $numbers);
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
            ((object) $marks)->add(function () use (&$seen) { return $seen; });
            $times -= 1;
        }
        return \implode(",", \array_map(fn ($read) => $read(), $marks));
    }

    public static function pipeline(array $numbers, array $prices): array
    {
        $odd = ((object) $numbers)->filter(fn ($n) => $n % 2 == 1);
        $labels = ((object) $odd)->map(fn ($n) => (string) ($n * 10));
        $groups = ((object) $numbers)->groupBy(fn ($n) => $n % 2 == 0 ? "even" : "odd");
        return [
            \implode(",", $labels),
            \implode(",", ((object) $numbers)->sortedBy(fn ($n) => -$n)),
            \implode(",", $groups["odd"] ?? []),
            \implode(",", \array_keys(((object) $labels)->associateBy(fn ($label) => $label))),
            \implode(",", \array_keys(((object) $prices)->filterValues(fn ($price) => $price < 100))),
            \implode(",", ((object) $prices)->filter(fn ($price) => $price < 100)),
            \implode(",", ((object) $prices)->map(fn ($price) => $price * 2)),
        ];
    }

    public static function total(array $numbers): int { return ((object) $numbers)->sumOf(fn ($n) => $n * 2) + ((object) $numbers)->first(fn ($n) => $n > 2) + (((object) $numbers)->any(fn ($n) => $n > 9) ? 1 : 0); }

    public static function failures(array $numbers): array
    {
        $messages = [];
        try {
            ((object) $numbers)->first(fn ($n) => $n > 100);
        } catch (OutOfRangeException $missing) {
            ((object) $messages)->add(((object) $missing)->getMessage());
        }
        try {
            ((object) $numbers)->map(fn ($n) => $n > 2 ? throw new OutOfRangeException((string) $n) : $n);
        } catch (OutOfRangeException $stopped) {
            ((object) $messages)->add(((object) $stopped)->getMessage());
        }
        return $messages;
    }

    public static function captured(array $numbers): array
    {
        $seen = $numbers;
        $kept = ((object) $seen)->filter(function ($n) use (&$seen) {
            ((object) $seen)->add($n);
            return true;
        });
        $grown = \count($seen);
        $size = function () use (&$seen) { return \count($seen); };
        ((object) $seen)->add(0);
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
}
