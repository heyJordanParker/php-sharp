<?php declare(strict_types=1); namespace Paging;

abstract class DatabaseEntity
{
    public function __construct(public readonly int $id)
    {
    }
}
