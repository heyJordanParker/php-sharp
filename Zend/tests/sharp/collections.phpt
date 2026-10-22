--TEST--
PHP# lists, maps and tuples are PHP arrays: literals, indexes, loops, unpacks and calls into plain PHP
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

require __DIR__ . '/harness/fixture.inc';
require __DIR__ . '/harness/Slots.inc';
fixture('Collections');

$cart = new \Demo\Cart();
echo $cart->fill([new \Demo\Item('pie', 7)]), "\n";
echo implode(',', $cart->prices()), ' ', Summary::sum($cart->prices()), "\n";
echo array_is_list($cart->items) ? 'list' : 'map', ' ', count($cart->items), "\n";
echo $cart->bump('items'), ' ', $cart->bump('extra'), "\n";
var_dump($cart->has(7), $cart->has(5));
echo implode(',', $cart->withPrices([1, 2], [9])), "\n";
// A tuple is a PHP list: its named read, its unpack and a loop that unpacks each tuple.
echo json_encode($cart->totals()), ' ', $cart->described(), "\n";

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
[10,2] 10/2 10/2 tea:3 pie:7
Cannot indirectly modify private(set) property Demo\Cart::$items from global scope
