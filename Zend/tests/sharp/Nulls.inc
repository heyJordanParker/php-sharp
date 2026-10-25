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

    public function through(?\Demo\Nulls $other, ?int $value): ?int
    {
        return ((object) $other)?->same($value) ?? ((object) $other)?->either(7, null);
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

    private ?\Closure $doubler = null;

    public function local(?\Closure $function, int $value): ?int { return ($function === null ? null : $function($value)); }

    public function property(?\Closure $function, int $value): ?int
    {
        $this->doubler = $function;
        return ((${'nullsafe#1'} = $this->doubler) === null ? null : ${'nullsafe#1'}($value));
    }

    public function called(?\Closure $function, int $value): ?int
    {
        $this->doubler = $function;
        return ((${'nullsafe#1'} = $this->held()) === null ? null : ${'nullsafe#1'}($value));
    }

    public function held(): ?\Closure { return $this->doubler; }

    public function greeting(?string $name = null): string { return $name ?? "nobody"; }

    private ?string $nickname = null;

    public function currentNickname(): ?string { return $this->nickname; }
}
