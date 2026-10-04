<?php declare(strict_types=1); namespace Demo;

class Calc
{
    public static function make(int $start): int
    {
        return $start * 10;
    }

    public function twice(int $value): int
    {
        return $value * 2;
    }

    public function run(int $value): int
    {
        $total = $this->twice($value);
        $total += \Demo\Calc::make(2);
        return $total;
    }
}
