<?php

namespace Demo;

class Cell
{
    public function __construct(public int $value) {}
}

final class LooseCounter
{
    /** @var int */
    public $value;

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
