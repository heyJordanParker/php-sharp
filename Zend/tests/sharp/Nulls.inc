<?php declare(strict_types=1); namespace Demo;

class Nulls
{
    final public static function none(): ?\Demo\Nulls
    {
        return null;
    }

    final public function same(?int $value): ?int
    {
        return $value;
    }

    final public function either(?int $first, ?int $second): int
    {
        return $first ?? $second ?? 0;
    }

    final public function fill(?int $value): int
    {
        $filled = $value;
        $filled ??= $this->either(7, null);
        $value ??= 8;
        return $filled + $value;
    }

    final public function through(?\Demo\Nulls $other, ?int $value): ?int
    {
        return ((object) $other)?->same($value) ?? ((object) $other)?->either(7, null);
    }

    final public function size(?\Demo\Box $box): ?int
    {
        return $box?->size;
    }

    final public function typed(?int $value): int
    {
        $found = null;
        $found ??= $value;
        $base = 10;
        $total = $base;
        $total += $found ?? 1;
        return $total;
    }

    final public function countTo(int $limit): int
    {
        $total = 0;
        for ($step = 1; $step <= $limit; $step++) {
            $total += $step;
        }
        return $total;
    }

    final public function missing(?int $value): bool
    {
        return $value === null;
    }

    final public function present(?string $value): bool
    {
        return $value !== null;
    }

    final public function absent(?bool $value): bool
    {
        return null === $value;
    }

    private ?\Closure $doubler = null;

    final public function local(?\Closure $function, int $value): ?int { return ($function === null ? null : $function($value)); }

    final public function property(?\Closure $function, int $value): ?int
    {
        $this->doubler = $function;
        return ((${'nullsafe#1'} = $this->doubler) === null ? null : ${'nullsafe#1'}($value));
    }

    final public function called(?\Closure $function, int $value): ?int
    {
        $this->doubler = $function;
        return ((${'nullsafe#1'} = $this->held()) === null ? null : ${'nullsafe#1'}($value));
    }

    final public function held(): ?\Closure { return $this->doubler; }
}
