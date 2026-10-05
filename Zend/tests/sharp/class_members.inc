<?php

namespace Lib;

require __DIR__ . '/Registry.inc';
require __DIR__ . '/Members.sharp';

use Demo\Members;

for ($i = 0; $i < 100; $i++) {
    $start = Members::start();
    $order = Members::order();
    $label = Members::label();
    $version = Members::version();
    $count = Members::bump();
}

var_dump($start, $order, $label, $version, $count);
var_dump(Members::NEXT, Members::$sorted, Members::pick());

Registry::$label = 'changed';
var_dump(Members::label());

// A loop over a static property array reads each element's value, even one a reference shares.
$kept = &Registry::$items[0];
for ($i = 0; $i < 50; $i++) {
    $ones = Members::ones();
}
var_dump($ones);

foreach (['missing', 'hidden', 'late'] as $method) {
    try {
        Members::$method();
    } catch (\Error $e) {
        echo $e->getMessage(), "\n";
    }
}
