<?php declare(strict_types=1); namespace Demo;

use SplFixedArray;

class Cart
{
    public private(set) array $items = [];
    private array $counts = [];

    public function fill(array $extra): int
    {
        $all = [new \Demo\Item("tea", 3), new \Demo\Item("cake", 5)];
        $all[1] = $extra[0];
        $this->items = $all;
        $this->counts["items"] = \count($this->items);
        $this->counts["extra"] = \count($extra);
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->cents;
        }
        foreach ($this->counts as $name => $size) {
            $total += \strlen($name) * $size;
        }
        return $total + \SplFixedArray::fromArray($this->prices())->getSize();
    }

    public function prices(): array
    {
        $prices = [];
        foreach ($this->items as $index => $item) {
            $prices[$index] = $item->cents;
        }
        return $prices;
    }

    public function bump(string $name): int
    {
        $this->counts[$name] += 1;
        return $this->counts[$name];
    }

    public function has(int $cents): bool
    {
        return \in_array($cents, $this->prices(), true);
    }
}

class Item
{
    public function __construct(public readonly string $name, public readonly int $cents)
    {
    }
}
