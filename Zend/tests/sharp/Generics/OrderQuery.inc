<?php declare(strict_types=1); namespace Paging;

class OrderQuery implements Query
{
    /** @param list<Order> $orders */
    public function __construct(private array $orders)
    {
    }

    final public function rows(): array { return $this->orders; }
}
