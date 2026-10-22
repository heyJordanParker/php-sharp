<?php declare(strict_types=1); namespace Demo;

use Lib\Slots;

class Cart
{
    public private(set) array $items = [];
    #[\Sharp\Crossing(['map', 'string', null])] private \Sharp\Map $counts;

    public function fill(array $extra): int
    {
        $this->items = [new \Demo\Item("tea", 3)];
        $this->items->add($extra[0]); // PHP# makes the array a Sharp\Collection
        $this->counts["items"] = \count($this->items);
        $this->counts["extra"] = \count($extra);
        $total = $this->items[0]->cents;
        foreach ($this->items as $item) {
            $total += $item->cents;
        }
        foreach ($this->counts as $name => $size) {
            if ($name === "items") {
                $total += $size * 10;
            }
        }
        return $total + ((object) \Lib\Slots::fromArray($this->prices()))->getSize();
    }

    public function prices(): array
    {
        $prices = [];
        foreach ($this->items as $item) {
            ((object) $prices)->add($item->cents); // PHP# makes the array a Sharp\Collection
        }
        return $prices;
    }

    public function bump(string $name): int
    {
        $this->counts[$name] = ($this->counts[$name] ?? 0) + 1;
        return $this->counts[$name] ?? 0;
    }

    public function has(int $cents): bool
    {
        return \array_find_key($this->prices(), (fn ($value) => fn ($element) => $element === $value)($cents)) !== null;
    }

    public function withPrices(array $before, array $after): array
    {
        return [...$before, ...$this->prices(), 0, ...$after];
    }

    public function totals(): array
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->cents;
        }
        return [$total, \count($this->items)];
    }

    public function described(): string
    {
        $totals = $this->totals();
        [$total, $count] = $this->totals();
        $described = "{$totals[0]}/{$totals[1]} {$total}/{$count}";
        $pairs = [["tea", 3], ["pie", 7]];
        foreach ($pairs as [$name, $cents]) {
            $described = "{$described} {$name}:{$cents}";
        }
        return $described;
    }
}
