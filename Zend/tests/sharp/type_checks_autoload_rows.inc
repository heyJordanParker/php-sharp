<?php

use Orchard\Picker;
use Orchard\Stall;

$asked = [];

$row = static function (string $name, callable $run): void {
    try {
        $result = $run();
    } catch (Throwable $thrown) {
        $result = get_class($thrown) . ': ' . $thrown->getMessage();
    }
    echo $name, ': ', $result, "\n";
};

// Crate<Apple> holds Apple as text, and no code has loaded Apple, so the check loads it to see that it extends Fruit.
$row('an out type argument', static fn (): string => Picker::weigh(Picker::apples()));

// The key type names Ripeness, which no code has loaded, so the check loads it to read the case "ripe" backs. The
// property holds a copy, so the autoloader's write to $stock mid-check changes only $stock.
$stock = ['ripe' => 3];
$row('a backed enum key', static function (): string {
    $stall = new Stall();
    $stall->stock = $GLOBALS['stock'];

    return $stall->ripe() . ', and $stock after the write: ' . $GLOBALS['stock']['ripe'];
});

// The closure returns Tray<Pear>, and nothing has loaded Tray or Pear.
$row('a function type', static fn (): string => var_export(Picker::packs(), true));

// The autoloader's own exception reaches the caller, not a TypeError.
$row('an autoloader that throws', static fn (): string => Picker::weigh(Picker::quinces()));

// A class the autoloader finds no file for gives the TypeError it always gave.
$row('a class with no file', static fn (): string => Picker::weigh(Picker::medlars()));

echo 'asked: ', implode(', ', $asked), "\n";
