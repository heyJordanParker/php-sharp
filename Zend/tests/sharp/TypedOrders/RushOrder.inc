<?php declare(strict_types=1); namespace Typed;

class RushOrder extends Order
{
    #[\Override] protected string $table = "rush_orders";
}
