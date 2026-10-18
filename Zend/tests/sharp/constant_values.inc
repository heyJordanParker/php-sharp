<?php

use ConstantValues\Billing;
use ConstantValues\Code;
use ConstantValues\Forms;
use ConstantValues\Plan;
use ConstantValues\Tier;

function constant_values(): string
{
    $method = new ReflectionMethod(Forms::class, 'described');
    $route = $method->getAttributes(Lib\Route::class)[0]->newInstance();
    $field = $method->getAttributes(Lib\Field::class)[0]->newInstance();

    return json_encode([
        'constants' => [
            Billing::GRACE_DAYS,
            Billing::FIRST_PLAN,
            Billing::TRIAL_LABEL,
            Billing::PRICE_CENTS,
            Billing::freePlan(),
            Billing::DEFAULT_KEY,
            Billing::doubled(4),
            (Billing::DOUBLE)(5),
            Billing::currentRate(),
        ],
        'plain PHP' => [
            Billing::PLANS,
            Billing::DEFAULT_PLAN === Plan::Free,
            Billing::DEFAULT_TIER === Tier\Free::instance(),
        ],
        'data case' => [Billing::freeTier(), Billing::freeTierMatches(), (new Billing())->fieldsFree()],
        'enum cases' => array_map(fn (Code $code) => $code->value, Code::cases()),
        'attribute' => [
            $route->path,
            $route->middleware[0],
            $route->middleware[1] === Tier\Free::instance(),
            ($route->options)(4),
            $field->label,
            $field->limit,
        ],
        'defaults' => Forms::described(),
        'patterns' => [
            Forms::grade(3),
            Forms::grade(999),
            Forms::grade(5),
            Forms::first('free'),
            Forms::label('Trial: 14 days'),
            Forms::free(Tier\Free::instance()),
            Forms::key('free'),
        ],
    ]);
}
