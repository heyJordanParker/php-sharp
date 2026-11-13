--TEST--
A PHP# call site whose receiver's class changes each turn reads each result as the method that ran gives it, whichever class the site met before, spec section 22: a PHP# method's result comes back as it is, the Map a Tally gives where Shelf declares an Iterable, and a plain PHP override's array crosses into the List Shelf declares, renumbered
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('Polymorphic');

class PlainShelf extends Polymorphic\Shelf
{
    public function names(): array
    {
        return [3 => 'plain'];
    }
}

$tally = new Polymorphic\Tally();
$plain = new PlainShelf();
echo implode("\n", Polymorphic\Reader::read([$tally, $plain, $tally, $plain, new Polymorphic\Shelf(), $tally])), "\n";
echo implode("\n", Polymorphic\Reader::read([$plain, $tally])), "\n";
?>
--EXPECT--
map tally
entries plain
map tally
entries plain
entries shelf
map tally
entries plain
map tally
