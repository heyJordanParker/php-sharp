<?php declare(strict_types=1); namespace Billing;

class Money
{
    public static private(set) int $equalityRuns = 0;

    public readonly int $cents;
    public readonly string $currency;

    public function __construct(int $cents, string $currency)
    {
        $this->cents = $cents;
        $this->currency = $currency;
    }

    public static function op_Equality(?\Billing\Money $a, ?\Billing\Money $b): bool { if ($a === null || $b === null) { return $a === $b; }

        \Billing\Money::$equalityRuns += 1;
        return $a->cents === $b->cents && $a->currency === $b->currency;
    }

    public function hash(): int { return $this->cents; }

    public static function op_Comparison(\Billing\Money $a, \Billing\Money $b): int { return $a->currency === $b->currency ? $a->cents <=> $b->cents : \strcmp($a->currency, $b->currency) <=> 0; }

    public static function op_Addition(\Billing\Money $a, \Billing\Money $b): \Billing\Money { return new \Billing\Money($a->cents + $b->cents, $a->currency); }

    public static function op_Subtraction(\Billing\Money $a, \Billing\Money $b): \Billing\Money { return new \Billing\Money($a->cents - $b->cents, $a->currency); }

    public static function op_Multiply(\Billing\Money $a, int $factor): \Billing\Money { return new \Billing\Money($a->cents * $factor, $a->currency); }

    public static function op_Division(\Billing\Money $a, int $parts): \Billing\Money { return new \Billing\Money(\intdiv($a->cents, $parts), $a->currency); }

    public static function op_Modulus(\Billing\Money $a, int $parts): \Billing\Money { return new \Billing\Money($a->cents % $parts, $a->currency); }

    public static function op_Exponent(\Billing\Money $a, int $power): \Billing\Money { return new \Billing\Money($a->cents ** $power, $a->currency); }

    public static function op_UnaryNegation(\Billing\Money $a): \Billing\Money { return new \Billing\Money(-$a->cents, $a->currency); }
}
