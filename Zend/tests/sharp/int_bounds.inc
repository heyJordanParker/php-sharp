<?php

// What each int_bounds*.phpt test prints: PHP#'s int bounds, its division overflows, and plain PHP's intdiv overflow.
function int_bounds(): string
{
    $output = var_export(IntBounds\Bounds::largest() === PHP_INT_MAX, true) . ' '
        . var_export(IntBounds\Bounds::smallest() === PHP_INT_MIN, true) . "\n";
    $output .= IntBounds\Bounds::overflow() . "\n";
    $output .= IntBounds\Bounds::floorOverflow() . "\n";
    try {
        intdiv(PHP_INT_MIN, -1);
    } catch (ArithmeticError $e) {
        $output .= $e->getMessage() . "\n";
    }

    return $output;
}

// Each round runs every line, so the JIT variants compile them before the last round, whose output is printed.
function int_bounds_rounds(): string
{
    for ($round = 0; $round < 100; $round++) {
        $output = int_bounds();
    }

    return $output;
}
