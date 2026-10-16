<?php declare(strict_types=1); namespace Paging;

class Repository
{
    /** @template TItem of DatabaseEntity
     *  @param Query<TItem> $query
     *  @return PaginatedList<TItem> */
    public function list(Query $query): PaginatedList { return new PaginatedList(((object) $query)->rows()); }
}
