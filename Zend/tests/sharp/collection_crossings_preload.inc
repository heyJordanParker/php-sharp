<?php

require __DIR__ . '/harness/Arrays.inc';
require __DIR__ . '/Crossings/Level.sharp';
require __DIR__ . '/Crossings/Weather.sharp';
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

function preloaded_counts_index(): mixed
{
    return Demo\Holdings::$counts['c'];
}

function preloaded_payload_index(Demo\Holdings $holdings): mixed
{
    return $holdings->payload['id'];
}

// A plain PHP subclass whose index read of its parent's property the optimizer and the JIT resolve.
final class PreloadedHoldings extends Demo\Holdings
{
    public function ownPayloadIndex(): mixed
    {
        return $this->payload['id'];
    }
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

// What plain PHP gets from `$read`, a read of a Map two of whose keys are one PHP array key: the ValueError it throws.
function preloaded_refused(Closure $read): string
{
    try {
        return get_debug_type($read());
    } catch (ValueError $e) {
        return $e->getMessage();
    }
}

function preloaded_mixed_codes(): mixed
{
    return Demo\Holdings::$mixedCodes;
}
