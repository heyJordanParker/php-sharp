<?php declare(strict_types=1); namespace Demo;

interface Shape
{
}

final class Circle implements Shape
{
    public function __construct(public float $radius)
    {
    }
}

final class Square implements Shape
{
    public function __construct(public float $side)
    {
    }
}

enum Status
{
    case Open;
    case Closed;
    case Archived;
}
