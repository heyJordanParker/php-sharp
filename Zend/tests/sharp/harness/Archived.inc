<?php declare(strict_types=1);

namespace Lib;

/** A parent that serializes its objects itself, as an Eloquent model does: `__serialize` writes what it keeps, and `__unserialize` reads it back. */
abstract class Archived
{
    public function __construct(protected string $kept)
    {
    }

    /** @return list<string> */
    public function __serialize(): array
    {
        return [$this->kept];
    }

    /** @param list<string> $data */
    public function __unserialize(array $data): void
    {
        $this->kept = $data[0];
    }

    public function kept(): string
    {
        return $this->kept;
    }
}
