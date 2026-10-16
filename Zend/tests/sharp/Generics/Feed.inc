<?php declare(strict_types=1); namespace Paging;

class Feed
{
    /** @param list<TItem> $items */
    public function __construct(private array $items)
    {
    }
    /** @return TItem */
    public function next(): DatabaseEntity { return $this->items[0]; }
}
