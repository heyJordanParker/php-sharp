<?php declare(strict_types=1); namespace Demo;

abstract class Shape
{
    abstract public function area(): float;

    abstract protected function name(): string;

    public function describe(): string
    {
        return $this->name();
    }
}
