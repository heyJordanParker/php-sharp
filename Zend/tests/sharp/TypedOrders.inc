<?php declare(strict_types=1); namespace Typed;

use Lib\TypedModel;

class Order extends TypedModel
{
    #[\Override] protected string $table = "orders";

    public function tableName(): string
    {
        return $this->table;
    }
}

class RushOrder extends Order
{
    #[\Override] protected string $table = "rush_orders";
}
