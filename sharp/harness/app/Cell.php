<?php

namespace App;

/** @template T */
final class Cell
{
    /** @var T|null */
    private mixed $value = null;

    /** @return Cell<T> */
    public static function empty(): self
    {
        return new self();
    }

    /** @param T $value */
    public function put(mixed $value): void
    {
        $this->value = $value;
    }

    /** @return T */
    public function get(): mixed
    {
        return $this->value;
    }
}
