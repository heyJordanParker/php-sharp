<?php declare(strict_types=1); namespace Demo;

interface Figure
{
}

final class Circle implements Figure
{
    public function __construct(public float $radius)
    {
    }
}

final class Square implements Figure
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
