<?php

declare(strict_types=1);

namespace Tests;

use App\Shop\Checkout;

// Reads the container for the tests, and keeps the mailer a test's own Before method saw.
final class Probe
{
    public static string $before = '';

    public static function mailer(): string
    {
        $checkout = (new \Sharp\Container())->get(Checkout::class);
        if (!$checkout instanceof Checkout) {
            throw new \LogicException('The container gives no ' . Checkout::class . '.');
        }

        return $checkout->usedMailer()->name();
    }
}
