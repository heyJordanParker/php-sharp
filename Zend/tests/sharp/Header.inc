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

trait Mixin
{
}
