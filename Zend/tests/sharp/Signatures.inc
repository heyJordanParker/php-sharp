<?php declare(strict_types=1); namespace Demo;

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

    public function label(int|string $id): int|string
    {
        if ($id === "") {
            return $this->id;
        }
        return $id;
    }

    public function matching(int|string|null $id): int|string|null
    {
        $found = null;
        if ($id === $this->id) {
            $found = $id;
        }
        return $found;
    }

    public function isSame(Signatures|int $other): bool
    {
        return $other === $this || $other === $this->id;
    }

    public function scale(int $first, int $second = 0, int $factor = 1): int
    {
        return ($first + $second) * $factor;
    }

    public function doubled(int ...$values): int
    {
        return $this->scale(...$values, factor: 2);
    }

    public function pay(int|float $amount): int|string
    {
        $this->amount += $amount;
        if ($amount > 0) {
            $this->receipt = $this->id;
        }
        return $this->receipt;
    }

    public static function sum(int ...$values): int
    {
        $total = 0;
        foreach ($values as $value) {
            $total += $value;
        }
        return $total;
    }

    public static function spread(int $first, int ...$values): int
    {
        return Signatures::sum($first, ...$values) + \max($first, ...$values);
    }

    public static function made(int ...$values): int|string
    {
        return new Signatures("none", ...$values)->label("");
    }
}
