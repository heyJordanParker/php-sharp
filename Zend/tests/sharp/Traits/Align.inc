<?php declare(strict_types=1); namespace Demo;

use Lib\Aligned;

enum Align: string implements Aligned
{
    case Left = "left";
    case Right = "right";

    public function label(): string { return "align"; }
}
