<?php declare(strict_types=1); namespace Demo;

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

    public function nested(int $extra): int
    {
        $outer = function (int $a) use ($extra) {
            $inner = fn ($b) => $a + $b + $extra + $this->base;
            return $inner(1);
        };
        return $outer(2);
    }
}
