<?php

use Demo\ChargeError;
use Demo\Gateway;
use Demo\Lookup;

/** The class of `$value` with the type arguments it carries, as `Sharp\Result\Ok<int, Demo.ChargeError>`. */
function typed(object $value): string
{
    return $value::class . '<' . implode(', ', (new ReflectionObject($value))->getTypeArguments()) . '>';
}

/** Each row runs one rule of spec section 31 on the objects its PHP# code made. */
function results(): array
{
    $gateway = new Gateway();

    return [
        typed($gateway->charge(5)),
        typed($gateway->charge(0)),
        $gateway->charge(5)->value->total,
        typed($gateway->doubled(5)),
        $gateway->doubled(5)->value,
        typed($gateway->doubled(0)),
        $gateway->doubled(0)->error->message,
        $gateway->total(7)->value,
        typed($gateway->total(0)),
        $gateway->later(7)->value,
        typed($gateway->later(0)),
        typed($gateway->receipts(3)),
        $gateway->receipts(3)->value[0]->total,
        typed($gateway->receipts(0)),
        typed($gateway->written()),
        $gateway->describe($gateway->charge(9)),
        $gateway->describe($gateway->charge(0)),
        $gateway->failed($gateway->charge(0)),
        $gateway->failed($gateway->charge(9)),
        $gateway->message(ChargeError\Expired::instance()),
        $gateway->message(new ChargeError\Declined('no funds')),
        $gateway->same($gateway->doubled(0), $gateway->doubled(0)),
        $gateway->same($gateway->doubled(0), $gateway->total(0)),
        $gateway->same($gateway->doubled(2), $gateway->total(4)),
        $gateway->same($gateway->doubled(2), $gateway->doubled(3)),
        typed($gateway->missing()),
        typed($gateway->missingText()),
        $gateway->missing() === $gateway->writtenMissing(),
        $gateway->missing() === $gateway->missingText(),
        typed($gateway->found(4)),
        // Plain PHP gives a generic case no type arguments, so it gets the bounds.
        typed(new Sharp\Result\Ok(5)),
        typed(Lookup\Missing::instance()),
        (new ReflectionClass(ChargeError\Expired::class))->getParentClass()->getName(),
        ChargeError\Expired::instance() instanceof Sharp\Error,
    ];
}
