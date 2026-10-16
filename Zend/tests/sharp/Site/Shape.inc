<?php declare(strict_types=1); namespace Site;

abstract class Shape
{
    abstract public function area(): float;

    public function sides(): int
    {
        return 0;
    }
}
