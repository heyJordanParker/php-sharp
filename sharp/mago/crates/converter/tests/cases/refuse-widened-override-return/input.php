<?php

declare(strict_types=1);

namespace App;

// A `Map` returns `Sharp\Map|array` in the PHP PHP# compiles to, which the plain PHP parent's `array` doesn't hold, and
// PHP links an override only when it returns at most what the parent's PHP returns (spec section 22).
final class SalesReport extends Report
{
    /** @return array<string, int> */
    public function totals(): array
    {
        return ['north' => 3];
    }
}
