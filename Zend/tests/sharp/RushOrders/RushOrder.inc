<?php declare(strict_types=1); namespace Rush;

class RushOrder extends Order
{
    #[\Override] protected $table = "rush_orders";
}
