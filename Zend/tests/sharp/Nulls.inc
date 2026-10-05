<?php declare(strict_types=1); namespace Demo;

class Nulls
{
    public static function none(): ?\Demo\Nulls
    {
        return null;
    }

    public function same(?int $value): ?int
    {
        return $value;
    }

    public function must(?int $value): int
    {
        return $value;
    }

    public function either(?int $first, ?int $second): int
    {
        return $first ?? $second ?? 0;
    }

    public function fill(?int $value): int
    {
        $filled = $value;
        $filled ??= $this->must(7);
        $value ??= 8;
        return $filled + $value;
    }

    public function itself(): \Demo\Nulls
    {
        return $this;
    }

    public function through(?\Demo\Nulls $other, ?int $value): ?int
    {
        return $other?->same($value) ?? $other?->itself()?->must(7);
    }

    public function size(?\Demo\Box $box): ?int
    {
        return $box?->size;
    }

    public function typed(?int $value): int
    {
        $found = null;
        $found ??= $value;
        $base = 10;
        $total = $base;
        $total += $found ?? 1;
        return $total;
    }

    public function countTo(int $limit): int
    {
        $total = 0;
        for ($step = 1; $step <= $limit; $step++) {
            $total += $step;
        }
        return $total;
    }

    public function missing(?int $value): bool
    {
        return $value === null;
    }

    public function present(?string $value): bool
    {
        return $value !== null;
    }

    public function absent(?bool $value): bool
    {
        return null === $value;
    }
}
