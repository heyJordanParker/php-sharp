<?php declare(strict_types=1); namespace Rush;

use Lib\Model;

class Order extends Model
{
    #[\Override] protected $table = "orders";

    public function tableName(): string
    {
        return $this->table;
    }
}

class RushOrder extends Order
{
    #[\Override] protected $table = "rush_orders";
}
