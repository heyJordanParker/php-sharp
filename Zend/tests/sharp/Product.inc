<?php declare(strict_types=1); namespace Demo;

use Lib\Action;
use Lib\Entity;
use Lib\Field;

#[Entity(label: "Products", order: 2 * 3), Searchable]
#[Entity("Stock")]
class Product
{
    #[Field] private int $stock = 0;
    #[Field(label: "Name", limit: PHP_INT_MAX)] public string $name = "";

    public function __construct(#[Field(label: null)] public readonly int $id, #[Field] int $start = 1)
    {
        $this->stock = $start;
    }

    #[Action(true, -1.5)]
    #[Retry(3)]
    public function restock(#[Field("amount")] int $amount): int
    {
        $this->stock += $amount;
        return $this->stock;
    }
}
