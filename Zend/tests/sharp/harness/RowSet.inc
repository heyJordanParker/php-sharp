<?php declare(strict_types=1);

namespace Lib;

/** @implements \ArrayAccess<int, list<int>> */
final class RowSet implements \ArrayAccess
{
    /** @param list<list<int>> $rows */
    public function __construct(private array $rows)
    {
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->rows[$offset]);
    }

    /** @return list<int> */
    public function offsetGet(mixed $offset): array
    {
        return $this->rows[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->rows[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->rows[$offset]);
    }
}
