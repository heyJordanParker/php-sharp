<?php declare(strict_types=1);

namespace Lib;

// A plain PHP parent whose `__get` serves the relations its subclass loaded, as Eloquent's Model does: an `Item` for
// each name but `wrong`, for which it gives an object of another class.
abstract class Catalog
{
    public function __get(string $name): mixed
    {
        return $name === 'wrong' ? new \stdClass() : new Item($name);
    }
}

final class Item
{
    public function __construct(public string $name)
    {
    }
}
