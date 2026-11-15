--TEST--
PHP# an indexer is a property that takes a parameter, as C#'s: cart[0] runs its get, cart[0] = x its set, cart[0] += x both, cart[i] ?? x and an interface's indexer read through get, and plain PHP's $cart[0], isset and a write reach it through ArrayAccess, checked against its types, where a get-only indexer's set and every unset throw LogicException
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('Indexers');

echo json_encode(Indexers\Cart::run()), "\n";
$cart = new Indexers\Cart();
var_dump($cart instanceof ArrayAccess, $cart[1], isset($cart[0]));
$cart[1] = 'figs';
echo $cart[1], "\n";
$shelf = new Indexers\Shelf();
var_dump($shelf instanceof Indexers\Rows, isset($shelf[-1]), $shelf[3]);
foreach ([fn () => unset_offset($cart), fn () => $shelf[0] = 'x'] as $refused) {
    try {
        $refused();
    } catch (LogicException $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}
try {
    $cart['first'];
} catch (TypeError $error) {
    echo get_class($error), "\n";
}

function unset_offset(Indexers\Cart $cart): void
{
    unset($cart[0]);
}
?>
--EXPECT--
["tea","tea!","honey","row 2","none","row 0"]
bool(true)
string(3) "jam"
bool(true)
figs
bool(true)
bool(false)
string(5) "row 3"
LogicException: `Indexers.Cart`'s indexer has no unset, as a C# indexer has none.
LogicException: `Indexers.Shelf`'s indexer has no set, as a C# indexer has none.
TypeError
