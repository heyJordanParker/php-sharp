<?php declare(strict_types=1);

namespace Lib;

class Row
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }
}
