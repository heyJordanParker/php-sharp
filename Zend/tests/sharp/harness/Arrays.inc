<?php

// Plain PHP functions a PHP# Map or Set crosses into, each saying what it received.
function array_keys_of(array $values): string
{
    return get_debug_type($values) . ' ' . json_encode(array_keys($values));
}

/** @param array<array-key, mixed> $values */
function documented_keys_of($values): string
{
    return get_debug_type($values) . ' ' . json_encode(array_keys($values));
}

function type_of_mixed(mixed $value): string
{
    return get_debug_type($value);
}

function type_of_untyped($value): string
{
    return get_debug_type($value);
}

function type_of_iterable(iterable $value): string
{
    return get_debug_type($value);
}

function write_array(array $prices): void
{
    $prices['tea'] = 9;
}

function write_mixed(mixed $prices): void
{
    $prices['tea'] = 9;
}

/** @return array<string, int> */
function price_list(): array
{
    return ['tea' => 3, '5' => 1];
}
