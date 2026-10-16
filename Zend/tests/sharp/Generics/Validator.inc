<?php declare(strict_types=1); namespace Paging;

interface Validator
{
    /** @param TItem $item */
    public function validate(DatabaseEntity $item): bool;
}
