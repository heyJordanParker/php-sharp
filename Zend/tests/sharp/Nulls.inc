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

    public function either(?int $first, ?int $second): int
    {
        return $first ?? $second ?? 0;
    }

    public function fill(?int $value): int
    {
        $filled = $value;
        $filled ??= $this->either(7, null);
        $value ??= 8;
        return $filled + $value;
    }

    public function itself(): \Demo\Nulls
    {
        return $this;
    }

    public function through(?\Demo\Nulls $other, ?int $value): ?int
    {
        return ((object) $other)?->same($value) ?? ((object) ((object) $other)?->itself())?->either(7, null);
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
}
