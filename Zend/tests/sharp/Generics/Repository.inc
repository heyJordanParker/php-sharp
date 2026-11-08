<?php declare(strict_types=1); namespace Paging;

class Repository
{
    /** @template TItem of DatabaseEntity
     *  @param Query<TItem> $query
     *  @return PaginatedList<TItem> */
    public function list(Query $query): PaginatedList { return new PaginatedList(\Sharp\Internal\returned($query->rows(), $query, 'rows', 'List<#0>, List<Any?>', 'Paging.Query.rows()')); }
}
