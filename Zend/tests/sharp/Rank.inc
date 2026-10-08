<?php declare(strict_types=1); namespace Demo;

enum Rank: int
{
    case Low = -1;
    case Mid = 0;
    case High = 2;

    public function above(\Demo\Rank $other): bool
    {
        return $this->value > $other->value;
    }
}
