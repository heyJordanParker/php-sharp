--TEST--
attributes<T>() builds an internal attribute class read in its own namespace, as its constructor runs in the scope of the declaration the attribute is written on, while plain PHP code outside the namespace still can't create it
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

var_dump(AttributeObjects\Marks\Reader::count());

try {
    new AttributeObjects\Marks\Marker();
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
int(1)
Cannot instantiate internal class AttributeObjects\Marks\Marker from global scope, outside namespace AttributeObjects\Marks
