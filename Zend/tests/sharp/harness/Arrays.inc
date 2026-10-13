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

// Plain PHP functions whose parameter names an object type a Sharp\Map or a Sharp\Set has.
function count_of_countable(Countable $values): string
{
    return get_debug_type($values) . ' ' . count($values);
}

function count_of_traversable(Traversable $values): string
{
    return get_debug_type($values) . ' ' . iterator_count($values);
}

function json_of(JsonSerializable $values): string
{
    return get_debug_type($values) . ' ' . json_encode($values);
}

function type_of_object(object $value): string
{
    return get_debug_type($value);
}

function count_of_map(Sharp\Map $values): string
{
    return get_debug_type($values) . ' ' . count($values);
}

/** @return Closure(array): string */
function array_counter(): Closure
{
    return static fn (array $counts): string => get_debug_type($counts) . ' ' . count($counts);
}
