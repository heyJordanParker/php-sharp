<?php declare(strict_types=1); namespace Demo;

class Item
{
    public function __construct(public readonly string $name, public readonly int $cents)
    {
    }
}
