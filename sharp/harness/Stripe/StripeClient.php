<?php

namespace Stripe;

final class StripeClient
{
    public static function charges(): self
    {
        return new self();
    }

    public function create(): \App\Charge
    {
        return new \App\Charge();
    }
}
