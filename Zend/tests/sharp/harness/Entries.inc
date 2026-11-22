<?php declare(strict_types=1);

namespace Lib;

/** A plain PHP base class that keeps every column in one array through __get, __set, __isset and __unset, as Eloquent's Model does, and names each call it takes. */
abstract class Entries
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    public function __get(string $key): mixed
    {
        echo "__get $key\n";

        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        echo "__set $key\n";
        $this->attributes[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        echo "__isset $key\n";

        return isset($this->attributes[$key]);
    }

    public function __unset(string $key): void
    {
        echo "__unset $key\n";
        unset($this->attributes[$key]);
    }

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return $this->attributes;
    }
}
