<?php

namespace Lib;

// A plain PHP `Traversable` with a `filter` of its own, as Laravel's `LazyCollection` has.
/** @implements \IteratorAggregate<int, int> */
final class Cursor implements \IteratorAggregate
{
    /** @param \Closure(int): bool $keep */
    public function filter(\Closure $keep): self
    {
        echo "Cursor::filter\n";

        return $this;
    }

    /** @return \Generator<int, int, mixed, void> */
    public function getIterator(): \Generator
    {
        yield 4;
        yield 5;
        yield 6;
    }
}
