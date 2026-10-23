<?php declare(strict_types=1);

namespace Lib;

/**
 * A plain PHP library's fixed number of slots, each empty until a value is put in it, which takes a PHP# type argument
 * through its docblock template.
 *
 * @template T
 */
final class Slots
{
    /** @var array<int, T> */
    private array $values = [];

    public function __construct(private int $size)
    {
    }

    /**
     * @param list<T> $values
     *
     * @return self<T>
     */
    public static function fromArray(array $values): self
    {
        return new self(count($values));
    }

    public function offsetExists(int $index): bool
    {
        return $index < $this->size && isset($this->values[$index]);
    }

    public function getSize(): int
    {
        return $this->size;
    }
}
