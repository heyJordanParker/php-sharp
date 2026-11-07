<?php

declare(strict_types=1);

namespace Proof\Shop;

use Acme\Format\Lines;
use Acme\Format\Record;

// Values the engine checks where they enter PHP#: an `(int)` of a property only PHPDoc types `int` stays, since the
// property may hold `"5"`, and an `iterable` the body loops over by value alone takes any keys and class. A keyed
// loop over a `string`-keyed array reads the key `'42'`, which PHP holds as the `int` 42, only as text and as a key.
final class Stock
{
    /** @param array<string, int> $prices */
    public function listed(array $prices): string
    {
        $listed = '';
        foreach ($prices as $sku => $price) {
            $listed .= $sku . '=' . $price . ';';
        }

        return $listed;
    }

    /**
     * @param array<string, int> $prices
     * @param array<string, bool> $wanted
     * @return array<string, int>
     */
    public function kept(array $prices, array $wanted): array
    {
        $kept = [];
        foreach ($prices as $sku => $price) {
            if (isset($wanted[$sku])) {
                $kept[$sku] = $price;
            }
        }

        return $kept;
    }

    public function quantity(Record $record): int
    {
        return (int) $record->quantity;
    }

    // A `(string)` of a property only PHPDoc types `string` converts what it holds, `5` or `null`, to text.
    public function coded(Record $record): string
    {
        return (string) $record->code . '/' . (string) $record->note;
    }

    // A loop over the values of a plain PHP collection is PHP's `foreach` over the object.
    public function counted(Lines $lines): string
    {
        $joined = '';
        foreach ($lines as $line) {
            $joined .= $line;
        }

        return $joined;
    }

    /** @param iterable<string> $names */
    public function joined(iterable $names): string
    {
        $joined = '';
        foreach ($names as $name) {
            $joined .= $name;
        }

        return $joined;
    }
}
