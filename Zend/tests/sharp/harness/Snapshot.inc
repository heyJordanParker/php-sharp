<?php declare(strict_types=1);

namespace Lib;

/** A parent that serializes its objects through `__serialize`, and keeps the data its `__unserialize` gets. */
abstract class Snapshot
{
    /** @var array<array-key, mixed> */
    public static array $restored = [];

    /** @return array<string, mixed> */
    public function __serialize(): array
    {
        return ['kept' => true];
    }

    /** @param array<array-key, mixed> $data */
    public function __unserialize(array $data): void
    {
        self::$restored = $data;
    }
}
