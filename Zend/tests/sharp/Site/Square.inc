<?php declare(strict_types=1); namespace Site;

class Square extends Shape
{
    #[\Override] public function area(): float
    {
        return 4.0;
    }

    #[\Override] public function sides(): int
    {
        return parent::sides() + 4;
    }
}
