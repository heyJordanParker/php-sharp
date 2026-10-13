--TEST--
A plain PHP write of a whole List property walks every element and makes an int in a float's place a float, and an append writes an element plain PHP never checks
--FILE--
<?php
require __DIR__ . '/type_arguments_entry.inc';

use Checks\Tally;

$tally = new Tally();
attempt(function () use ($tally) {
    $tally->amounts[] = 2.5;
    return json_encode($tally->amounts, JSON_PRESERVE_ZERO_FRACTION);
});
attempt(function () use ($tally) {
    $tally->amounts = [1, 2.5];
    return json_encode($tally->amounts, JSON_PRESERVE_ZERO_FRACTION);
});
attempt(function () use ($tally) {
    $tally->amounts = [1, 'x'];
});
?>
--EXPECT--
[2.5]
[1.0,2.5]
Cannot assign array to property Checks\Tally::$amounts of type List<float>
