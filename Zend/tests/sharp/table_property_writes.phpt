--TEST--
Plain PHP writing a PHP array to a PHP# Map or Set property, instance or static, crosses it into the object, in strict mode too, and an index write through the property still changes it
--FILE--
<?php
declare(strict_types=1);

require __DIR__ . '/Larder.sharp';

use Demo\Larder;

// Each round runs every row, so the JIT variants compile them before the last round, whose output is printed.
for ($round = 0; $round < 100; $round++) {
    ob_start();
    $larder = new Larder([]);
    $larder->stock = ['5' => 1, 'sku' => 2];
    $larder->labels = ['5', 'tag'];
    $larder->ripeness = ['ripe' => 3];
    $larder->shelves = ['top' => [1, 2, 2]];
    $larder->spare = ['5' => 4];
    $larder->counted = ['5' => 6, 'gone' => 0];
    Larder::$shared = ['5' => 5];
    Larder::$tags = ['5'];
    $larder->bins = ['5'];
    $larder->crates = ['box' => [['ripe' => 7]]];
    $larder->aisles = [1 => 8];
    echo 'the objects: ', implode(' ', array_map(get_debug_type(...), [$larder->stock, $larder->labels, $larder->ripeness, $larder->shelves, $larder->spare, $larder->counted, Larder::$shared, Larder::$tags, $larder->bins, $larder->crates, $larder->crates['box'][0], $larder->aisles])), "\n";
    echo 'PHP# reads them back: ', implode(' ', $larder->read()), "\n";

    $larder->stock['6'] = 6;
    $larder->labels[] = 'new';
    Larder::$shared['7'] = 7;
    Larder::$tags[] = 'new';
    echo 'index writes: ', json_encode([$larder->stock, $larder->labels, Larder::$shared, Larder::$tags]), "\n";

    $reference = &$larder->stock;
    $reference = ['8' => 8];
    unset($reference);
    echo 'through a reference: ', get_debug_type($larder->stock), ' ', json_encode($larder->stock), "\n";

    $array = ['9' => 9];
    $larder->stock = &$array;
    echo 'assigned by reference: ', get_debug_type($larder->stock), ' ', get_debug_type($array), "\n";
    unset($array);

    (new ReflectionProperty(Larder::class, 'stock'))->setValue($larder, ['10' => 10]);
    echo 'through reflection: ', get_debug_type($larder->stock), ' ', json_encode($larder->stock), "\n";

    try {
        $larder->ripeness = ['rotten' => 1];
    } catch (ValueError $e) {
        echo 'a value no case backs: ', $e->getMessage(), ', kept ', json_encode($larder->ripeness), "\n";
    }
    try {
        $larder->crates = ['box' => [['ripe' => 1], ['rotten' => 1]]];
    } catch (ValueError $e) {
        echo 'a nested value no case backs: ', $e->getMessage(), ', kept ', json_encode($larder->crates), "\n";
    }
    try {
        $larder->aisles = ['first' => 1];
    } catch (TypeError $e) {
        echo 'a string key of an int-backed enum: ', $e->getMessage(), "\n";
    }
    try {
        $larder->stock = 'text';
    } catch (TypeError $e) {
        echo 'a string: ', $e->getMessage(), "\n";
    }
    $output = ob_get_clean();
}
echo $output;
?>
--EXPECT--
the objects: Sharp\Map Sharp\Set Sharp\Map Sharp\Map Sharp\Map Sharp\Map Sharp\Map Sharp\Set Sharp\Set Sharp\Map Sharp\Map Sharp\Map
PHP# reads them back: 1 true 3 2 4 1:6 5 true true 7 8
index writes: [{"5":1,"sku":2,"6":6},["5","tag","new"],{"5":5,"7":7},["5","new"]]
through a reference: Sharp\Map {"8":8}
assigned by reference: Sharp\Map Sharp\Map
through reflection: Sharp\Map {"10":10}
a value no case backs: "rotten" is not a valid backing value for enum Demo\Ripeness, kept {"ripe":3}
a nested value no case backs: "rotten" is not a valid backing value for enum Demo\Ripeness, kept {"box":[{"ripe":7}]}
a string key of an int-backed enum: Demo\Aisle::from(): Argument #1 ($value) must be of type int, string given
a string: Cannot assign string to property Demo\Larder::$stock of type Sharp\Map
