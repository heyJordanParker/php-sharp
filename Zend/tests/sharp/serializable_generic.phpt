--TEST--
PHP# a generic class may be PHP's Serializable: serialize keeps its type arguments ahead of the text its serialize writes, and unserialize gives that text alone to its unserialize
--INI--
error_reporting=E_ALL & ~E_DEPRECATED
--FILE--
<?php
require __DIR__ . '/harness/Stored.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('Shelves');

$shelf = Shelves\Shelves::numbers('front');
$serialized = serialize($shelf);
echo substr($serialized, 0, 2), "\n";
$copy = unserialize($serialized);
echo $copy->label, "\n";
echo implode(', ', (new ReflectionObject($copy))->getTypeArguments()), "\n";
var_dump(Shelves\Shelves::holdsNumbers($copy));
var_dump(unserialize(serialize([$copy, $copy]))[1]->label);
?>
--EXPECT--
C:
front
int
bool(true)
string(5) "front"
