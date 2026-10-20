<?php

namespace CallerChecked;

/** A package's model, whose methods declare no return type, as Eloquent's do. */
abstract class Model
{
    public mixed $given = null;

    public function morphToMany($related, $name)
    {
        return $this->given ?? new Relation($name);
    }

    public function fail($reason)
    {
        throw new \LogicException($reason);
    }

    public function ids()
    {
        return $this->given ?? [1, 2];
    }

    public function labels()
    {
        return $this->given ?? ['a', 'b', 'c'];
    }

    public function counts()
    {
        return $this->given ?? ['a' => 1];
    }

    public function stream()
    {
        return $this->given ?? new \ArrayIterator([1, 2, 3]);
    }

    public function pair()
    {
        return $this->given ?? [[1, 2], 'two'];
    }
}

class Contact extends Model implements Taggable
{
}

final class Values implements IntSource
{
    public function __construct(private $value)
    {
    }

    public function read()
    {
        return $this->value;
    }
}
