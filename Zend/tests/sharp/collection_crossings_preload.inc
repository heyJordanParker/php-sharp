<?php

require __DIR__ . '/harness/Arrays.inc';
require __DIR__ . '/Crossings/Level.sharp';
require __DIR__ . '/Crossings/Holdings.sharp';
require __DIR__ . '/Crossings/Crossings.sharp';

// Plain PHP readers preloaded with the PHP# classes, so the optimizer and the JIT know each class and property they
// read, and must still leave each Map and Set read to the code that gives plain PHP its PHP array.
function preloaded_payload(Demo\Holdings $holdings): mixed
{
    return $holdings->payload;
}

function preloaded_payload_count(Demo\Holdings $holdings): int
{
    return count($holdings->payload);
}

function preloaded_rows(Demo\Holdings $holdings): mixed
{
    return $holdings->rows;
}

function preloaded_counts(): mixed
{
    return Demo\Holdings::$counts;
}

function preloaded_sizes(): mixed
{
    return Demo\Crossings::sizes();
}

function preloaded_sizes_merged(): array
{
    return array_merge(Demo\Crossings::sizes(), ['c' => 3]);
}

function preloaded_limits(): mixed
{
    return Demo\Holdings::LIMITS;
}
