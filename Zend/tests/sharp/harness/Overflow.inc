<?php

namespace Demo;

class Cell
{
    public function __construct(public int $value) {}
}

final class LooseCounter
{
    /** @var int The default keeps it `int` in PHP#, which reads an untyped property PHP starts at `null` as `int?`. */
    public $value = 0;

    /** @param int $value */
    public function __construct($value)
    {
        $this->value = $value;
    }
}

final class Probe
{
    public static function touch(int &$x): void {}
}
