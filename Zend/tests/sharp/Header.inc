<?php

namespace Lib;

interface Named
{
    public function name(): string;
}

class Entity
{
    public function id(): int
    {
        return 7;
    }
}

class Other
{
}

abstract class Shelf
{
    abstract public function copy(): Named;
}

final class Sealed
{
}

enum Suit
{
    case Hearts;
}

trait Mixin
{
}
