<?php

require __DIR__ . '/harness/Model.inc';
require __DIR__ . '/Accessors.sharp';
require __DIR__ . '/Shipment.sharp';

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
    $shipment = new Store\Shipment();
    $shipment->destination = $address;
    echo $address->city, ' ', $shipment->destination->city, ' ', var_export($shipment->getAttribute('destination') === $address, true), "\n";
}

foreach ([
    [Store\Shipment::class, 'destination'],
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