--TEST--
A Map's count() gives its size as PHP's count gives the array it crosses into, and its values().join() the text PHP's implode gives, on the same data, an empty Map included
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Text/StringMembers.sharp';
require __DIR__ . '/harness/fixture.inc';
fixture('MapReads');

foreach ([['b' => 'two', 'a' => 'one', 'c' => 'three'], []] as $array) {
    var_dump(MapReads\Reads::count($array) === count($array));
    var_dump(MapReads\Reads::joined($array, ', ') === implode(', ', $array));
    echo MapReads\Reads::joined($array, ', '), "\n";
}
?>
--EXPECT--
bool(true)
bool(true)
two, one, three
bool(true)
bool(true)

