<?php declare(strict_types=1); namespace Paging;

class EntityValidator implements Validator
{
    public function validate(DatabaseEntity $item): bool { return $item->id > 0; }
}
