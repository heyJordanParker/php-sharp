--TEST--
PHP# lists and maps are PHP arrays: literals, indexes, loops and calls into plain PHP
--FILE--
<?php

namespace Lib;

final class Summary
{
    public static function sum(array $values): int
    {
        return array_sum($values);
    }
}

require __DIR__ . '/Collections.sharp';

$cart = new \Demo\Cart();
echo $cart->fill([new \Demo\Item('pie', 7)]), "\n";
echo implode(',', $cart->prices()), ' ', Summary::sum($cart->prices()), "\n";
echo array_is_list($cart->items) ? 'list' : 'map', ' ', count($cart->items), "\n";
echo $cart->bump('items'), ' ', $cart->bump('extra'), "\n";
var_dump($cart->has(7), $cart->has(5));
echo implode(',', $cart->withPrices([1, 2], [9])), "\n";

try {
    $cart->items[] = new \Demo\Item('tart', 1);
} catch (\Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
35
3,7 10
list 2
3 2
bool(true)
bool(false)
1,2,3,7,0,9
Cannot indirectly modify private(set) property Demo\Cart::$items from global scope
