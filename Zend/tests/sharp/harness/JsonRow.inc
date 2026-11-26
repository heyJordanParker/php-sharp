<?php declare(strict_types=1);

namespace Lib;

/** A plain PHP base class that stores each column as JSON through __set and gives it back decoded through __get, as a framework's attribute cast does, so __get gives PHP arrays. */
class JsonRow
{
    /** @var array<string, string> */
    private array $attributes = [];

    /** @var array<string, mixed> */
    private array $cache = [];

    public function __get(string $name): mixed
    {
        return $this->cache[$name] ??= json_decode($this->attributes[$name], true);
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = json_encode($value);
        unset($this->cache[$name]);
    }
}
