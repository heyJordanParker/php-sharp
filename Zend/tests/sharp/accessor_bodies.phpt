--TEST--
PHP# accessor bodies run as property hooks: field and value, a validating set, a private set, a collection written back through set, an auto accessor beside a body, a set the constructor runs and a property over a plain PHP base
--FILE--
<?php

require __DIR__ . '/harness/Row.inc';
require __DIR__ . '/harness/Words.inc';
require __DIR__ . '/vendor/autoload.php';

// Each run repeats, so the JIT variants compile the accessors and methods before the second run.
for ($run = 0; $run < 2; $run++) {
    $product = new Store\Product();
    $product->sku = 'L-1';
    $product->name = '  Lamp  ';
    $product->price = 40;
    $product->restock(3);
    $product->restock(-9);
    $product->restock(2);
    $product->tag('Light');
    $product->tag('HOME');
    $product->tags = [...$product->tags, 'Sale'];
    $product->rating = 5;
    $product->rating = 3;
    echo $product->sku, ' ', $product->name, ' ', $product->price, ' ', $product->stock, ' ',
        implode(',', $product->tags), ' ', $product->rating, ' ', implode(',', $product->ratings), "\n";

    try {
        $product->price = -1;
    } catch (InvalidArgumentException $error) {
        echo $error->getMessage(), ' ', $product->price, "\n";
    }
    try {
        $product->stock = 9;
    } catch (Error $error) {
        echo $error->getMessage(), "\n";
    }
    try {
        $product->tags[] = 'outdoor';
    } catch (Error $error) {
        echo $error->getMessage(), "\n";
    }

    $address = new Store\Address('  sofia ', 'bg');
    echo $address->city, ' ', $address->label(), "\n";
    try {
        echo $address->region;
    } catch (Error $error) {
        echo $error->getMessage(), "\n";
    }
    $address->city = 'plovdiv ';
    $parcel = new Store\Parcel();
    $parcel->destination = $address;
    echo $address->city, ' ', $parcel->destination->city, ' ', var_export($parcel->getAttribute('destination') === $address, true), "\n";
}

foreach ([
    [Store\Parcel::class, 'destination'],
    [Store\Product::class, 'name'],
    [Store\Product::class, 'price'],
    [Store\Product::class, 'stock'],
    [Store\Product::class, 'tags'],
    [Store\Product::class, 'rating'],
    [Store\Address::class, 'city'],
    [Store\Address::class, 'region'],
] as [$class, $name]) {
    echo $class, '::$', $name, ' virtual: ', var_export((new ReflectionProperty($class, $name))->isVirtual(), true), "\n";
}
?>
--EXPECT--
L-1 Lamp 40 2 light,home,sale 0 5,3
A price is never negative. 40
Cannot modify private(set) property Store\Product::$stock from global scope
Indirect modification of Store\Product::$tags is not allowed
Sofia Sofia, BG
Cannot access protected property Store\Address::$region
Plovdiv Plovdiv true
L-1 Lamp 40 2 light,home,sale 0 5,3
A price is never negative. 40
Cannot modify private(set) property Store\Product::$stock from global scope
Indirect modification of Store\Product::$tags is not allowed
Sofia Sofia, BG
Cannot access protected property Store\Address::$region
Plovdiv Plovdiv true
Store\Parcel::$destination virtual: true
Store\Product::$name virtual: false
Store\Product::$price virtual: false
Store\Product::$stock virtual: false
Store\Product::$tags virtual: false
Store\Product::$rating virtual: false
Store\Address::$city virtual: false
Store\Address::$region virtual: false
