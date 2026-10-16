<?php

// Each orchard class loads the first time code names it, as a Composer app's classes do. The autoloader logs each class
// it is asked for in $asked. It throws for Quince, finds no Medlar, and, asked for Ripeness, writes to $stock, the
// array a check is walking at that moment.
$asked = [];
spl_autoload_register(static function (string $class) use (&$asked): void {
    if (!str_starts_with($class, 'Orchard\\')) {
        return;
    }
    $asked[] = $class;
    if ($class === 'Orchard\Quince') {
        throw new RuntimeException('The autoloader failed to load Orchard\Quince.');
    }
    if ($class === 'Orchard\Ripeness') {
        $GLOBALS['stock']['ripe'] = 99;
    }
    if ($class !== 'Orchard\Medlar') {
        require __DIR__ . '/Orchard/' . substr($class, strlen('Orchard\\')) . '.sharp';
    }
});
