--TEST--
An int plain PHP gives for a float becomes a float where it enters PHP#, through a type parameter, a nullable type and a union
--FILE--
<?php
require __DIR__ . '/type_arguments_entry.inc';

use Checks\Pages;

foreach ([Pages::floats(), Pages::nullableFloats(), Pages::floatsOrStrings()] as $cell) {
    $cell->put(2);
    var_dump($cell->get());
}
$floats = Pages::floats();
$floats->put(2);
var_dump(Pages::two($floats));
?>
--EXPECT--
float(2)
float(2)
float(2)
bool(true)
