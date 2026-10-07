<?php declare(strict_types=1); namespace Demo;

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
}
