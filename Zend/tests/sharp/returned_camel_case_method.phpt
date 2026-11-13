--TEST--
A PHP# call of a PHP# method a plain PHP override may replace gives the method's result as it is, whatever the case of the method's name: Sharp\Internal\returned finds a camelCase method by the name it declares, spec section 24, so the Map an override returns where its parent declares Iterable<[string, int]> stays the Map, as a lowercase method's does, where the check of a plain PHP result would put a Sequence over it
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('Tallies');

$report = new Tallies\Report();
$tally = new Tallies\Tally();
var_dump($report->camel($tally), $report->lower($tally));
?>
--EXPECT--
bool(true)
bool(true)
