<?php declare(strict_types=1);

namespace Lib;

/** A plain PHP base class that leaves PHP's ArrayAccess and Iterator protocols to a subclass, as a library's does. */
abstract class Grid implements \ArrayAccess, \Iterator
{
    abstract public function offsetExists(mixed $offset): bool;

    abstract public function offsetGet(mixed $offset): mixed;

    abstract public function offsetSet(mixed $offset, mixed $value): void;

    abstract public function offsetUnset(mixed $offset): void;

    abstract public function current(): mixed;

    abstract public function key(): mixed;

    abstract public function next(): void;

    abstract public function rewind(): void;

    abstract public function valid(): bool;
}
