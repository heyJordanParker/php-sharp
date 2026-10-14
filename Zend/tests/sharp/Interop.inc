<?php declare(strict_types=1); namespace Demo;

use Lib\Shipping;

class Interop
{
    public static function shipping(int $cents): string
    {
        return \shipping_label($cents);
    }

    public static function shippingIn(string $currency, int $cents): string
    {
        return \shipping_label($cents * 2, currency: $currency);
    }

    public static function freeAbove(): int { return \Lib\Shipping\FREE_ABOVE; }

    public static function largest(): int { return \PHP_INT_MAX; }
}
