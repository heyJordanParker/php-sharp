<?php

namespace Lib;

interface Named
{
    public function name(): string;
}

class Record
{
    public function id(): int
    {
        return 7;
    }
}

abstract class Shelf
{
    abstract public function copy(): Named;
}
