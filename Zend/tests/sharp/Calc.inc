<?php declare(strict_types=1); namespace Demo;

class Calc
{
    final public static function make(int $start): int
    {
        return $start * 10;
    }

    final public function twice(int $value): int
    {
        return $value * 2;
    }

    final public function run(int $value): int
    {
        $total = $this->twice($value);
        $total += \Demo\Calc::make(2);
        return $total;
    }
}
