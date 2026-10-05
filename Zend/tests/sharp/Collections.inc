<?php declare(strict_types=1); namespace Demo;

use SplFixedArray;

class Cart
{
    public private(set) array $items = [];
    private array $counts = [];

    public function fill(array $extra): int
    {
        $all = [new \Demo\Item("tea", 3), $extra[0]];
        $this->items = $all;
        $this->counts["items"] = \count($this->items);
        $this->counts["extra"] = \count($extra);
        $total = $all[0]->cents;
        foreach ($this->items as $item) {
            $total += $item->cents;
        }
        foreach ($this->counts as $name => $size) {
            if ($name == "items") {
                $total += $size * 10;
            }
        }
        return $total + \SplFixedArray::fromArray($this->prices())->getSize();
    }

    public function prices(): array
    {
        return \array_column($this->items, "cents");
    }

    public function bump(string $name): int
    {
        $this->counts[$name] = ($this->counts[$name] ?? 0) + 1;
        return $this->counts[$name] ?? 0;
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
