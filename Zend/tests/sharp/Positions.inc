<?php declare(strict_types=1); namespace Demo;

class Positions
{
    public function here(): \Sharp\Position
    {
        return new \Sharp\Position(__FILE__, 7, 16, 'Demo.Positions.here');
    }

    public \Sharp\Position $folder { get => new \Sharp\Position(__FILE__, 10, 31, 'Demo.Positions.folder'); }
}
