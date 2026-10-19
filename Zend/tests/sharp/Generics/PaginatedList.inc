<?php declare(strict_types=1); namespace Paging;

class PaginatedList
{
    /** @param list<TItem> $rows */
    public function __construct(private array $rows)
    {
    }
    /** @return TItem */
    final public function first(): DatabaseEntity { return $this->rows[0]; }
}
