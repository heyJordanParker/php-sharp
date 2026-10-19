<?php declare(strict_types=1); namespace Demo;

use Lib\Shipping;

class Interop
{
    final public static function shipping(int $cents): string
    {
        return \shipping_label($cents);
    }

    final public static function shippingIn(string $currency, int $cents): string
    {
        return \shipping_label($cents * 2, currency: $currency);
    }

    final public static function freeAbove(): int { return \Lib\Shipping\FREE_ABOVE; }

    final public static function largest(): int { return \Sharp\Int::MAX; }
}
