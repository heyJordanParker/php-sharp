<?php declare(strict_types=1); namespace Paging;

interface Query
{
    /** @return list<TItem> */
    public function rows(): array;
}
