<?php declare(strict_types=1); namespace Demo;

class ControlFlow
{
    public static function sign(int $value): string
    {
        if ($value > 0) {
            return "positive";
        } else if ($value < 0) {
            return "negative";
        } else {
            return "zero";
        }
    }

    public static function countdown(int $start): int
    {
        $steps = 0;
        while ($start > 0) {
            $start -= 1;
            $steps++;
        }
        do {
            $steps += 10;
        } while ($steps < 5);
        return $steps;
    }

    public static function sumOdd(int $limit): int
    {
        $total = 0;
        $n = 0;
        while (true) {
            $n++;
            if ($n > $limit) {
                break;
            }
            if ($n % 2 == 0) {
                continue;
            }
            $total += $n;
        }
        return $total;
    }

    public static function sumTo(int $limit): int
    {
        $total = 0;
        for ($i = 1; $i <= $limit; $i++) {
            $total += $i;
        }
        $rounds = 0;
        for ($rounds = 0; $rounds < 2; $rounds++, $total++) {
        }
        return $total;
    }

    public static function weigh(int $factor): int
    {
        $total = 0;
        foreach (Prices::all() as $price) {
            $total += $price * $factor;
        }
        foreach (Prices::all() as $slot => $price) {
            $total += $slot;
            $price = 0;
        }
        return $total;
    }
}
