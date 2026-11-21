<?php declare(strict_types=1);

namespace Lib;

/** A plain PHP base class that keeps every column in one array through __get and __set, as Eloquent's Model does. */
abstract class Columns
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        echo "__set $key\n";
        $this->attributes[$key] = $value;
    }

    /** @return array<string, mixed> */
    public function columns(): array
    {
        return $this->attributes;
    }
}
