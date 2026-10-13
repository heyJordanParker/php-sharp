<?php

declare(strict_types=1);

namespace Tests;

// Reads the container for the tests, and keeps the mailer a test's own Before method saw.
final class Probe
{
    public static string $before = '';

    public static function mailer(): string
    {
        return (new \Sharp\Container())->get('App\Shop\Checkout')->usedMailer()->name();
    }
}
