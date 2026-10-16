<?php declare(strict_types=1); namespace Demo;

use Lib\Aligned;

enum Tier: int implements Aligned
{
    case Low = 1;
    case High = 2;

    public function label(): string { return "level"; }
}
