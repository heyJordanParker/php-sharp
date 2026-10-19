<?php declare(strict_types=1); namespace Demo;

use Sharp\Math\Math;

class Signatures
{
    private int|string $receipt = "unpaid";
    public int|float $amount = 0;

    public function __construct(private int|string $id, int ...$values)
    {
        if (\count($values) > 0) {
            $this->id = Signatures::sum(...$values);
        }
    }

    final public function label(int|string $id): int|string
    {
        if ($id === "") {
            return $this->id;
        }
        return $id;
    }

    final public function matching(int|string|null $id): int|string|null
    {
        $found = null;
        if ($id === $this->id) {
            $found = $id;
        }
        return $found;
    }

    final public function isSame(Signatures|int $other): bool
    {
        return \is_int($other) ? $other === $this->id : $other === $this;
    }

    final public function scale(int $first, int $second = 0, int $factor = 1): int
    {
        return ($first + $second) * $factor;
    }

    final public function doubled(int ...$values): int
    {
        return $this->scale(...$values, factor: 2);
    }

    final public function pay(int|float $amount): int|string
    {
        $this->amount += $amount;
        if ($amount > 0) {
            $this->receipt = $this->id;
        }
        return $this->receipt;
    }

    final public static function sum(int ...$values): int
    {
        $total = 0;
        foreach ($values as $value) {
            $total += $value;
        }
        return $total;
    }

    final public static function spread(int $first, int ...$values): int
    {
        return Signatures::sum($first, ...$values) + Math::max($first, ...$values);
    }

    final public static function made(int ...$values): int|string
    {
        return ((object) new Signatures("none", ...$values))->label("");
    }
}
