<?php declare(strict_types=1);

namespace Lib;

/** A plain PHP base class with a `callable` parameter, as an ORM's model takes its callbacks. */
abstract class Model
{
    public function tap(callable $callback): static
    {
        $callback($this);
        return $this;
    }
}

interface Named
{
}

interface Sized
{
}

/** One member for each kind of type a plain PHP signature declares. */
abstract class Signatures
{
    public ?\Closure $done = null;

    abstract public function call(callable $callback): ?callable;

    abstract public function each(iterable $items): iterable;

    abstract public function any(object $value, mixed $extra): mixed;

    abstract public function same(self $other): static;

    abstract public function stop(): never;

    abstract public function nothing(): void;

    abstract public function both(Named&Sized $value): (Named&Sized)|null;

    abstract public function either((Named&Sized)|int $value): false|string;

    abstract public function always(): true;

    abstract public function empty(): null;
}
