--TEST--
Plain PHP reads a PHP# class's Map property as its PHP array, spec section 12: an index read, array_map, and both inside a method of a plain PHP subclass
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('Crossings');

final class PlainOffers extends Demo\Offers
{
    public function bumped(): int
    {
        return $this->offers['bump'];
    }

    public function doubled(): array
    {
        return array_map(fn (int $count) => $count * 2, $this->offers);
    }
}

// Each round runs every read, so the JIT variants compile them before the last round, whose output is printed.
for ($round = 0; $round < 100; $round++) {
    ob_start();
    $offers = new Demo\Offers();
    var_dump($offers->offers['bump']);
    var_dump(array_map(fn (int $count) => $count * 2, $offers->offers));
    $plain = new PlainOffers();
    var_dump($plain->bumped(), $plain->doubled());
    $output = ob_get_clean();
}
echo $output;
?>
--EXPECT--
int(3)
array(2) {
  ["bump"]=>
  int(6)
  ["pin"]=>
  int(10)
}
int(3)
array(2) {
  ["bump"]=>
  int(6)
  ["pin"]=>
  int(10)
}
