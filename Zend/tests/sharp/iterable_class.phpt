--TEST--
A class whose header names Iterable<T> is an Iterable<T> of its elements(): a PHP# loop, an Iterable<T> parameter, toList() and the lazy operations read each element, and plain PHP's foreach and iterator_to_array() receive the object as a Traversable whose keys count from 0, or whose [key, value] tuples give their key and value
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('Bags');

$report = new Bags\Report();
$bag = $report->made();
$tally = new Bags\Tally();

var_dump($report->numbers($bag));
var_dump($report->tallied($bag));
var_dump($report->paid($bag));
var_dump($report->first($bag));
var_dump($report->counted($tally));
var_dump($report->entries($tally));
var_dump($report->left(new Bags\Countdown()));
var_dump($report->holdsOrders($bag), $report->holdsNumbers($bag));

var_dump($bag instanceof Traversable, $bag instanceof IteratorAggregate);
foreach ($bag as $i => $order) {
    echo $i, ' ', $order->number, "\n";
}
foreach ($tally as $name => $count) {
    echo $name, ' => ', $count, "\n";
}
var_dump(iterator_to_array($tally));
foreach (new Bags\Countdown() as $i => $step) {
    echo $i, ' ', json_encode($step), "\n";
}
?>
--EXPECT--
string(12) "A-1 A-2 A-3 "
int(3)
int(2)
string(3) "A-1"
int(16)
int(2)
int(3)
bool(true)
bool(false)
bool(true)
bool(true)
0 A-1
1 A-2
2 A-3
tea => 3
pie => 7
array(2) {
  ["tea"]=>
  int(3)
  ["pie"]=>
  int(7)
}
0 {"left":2}
1 {"left":1}
