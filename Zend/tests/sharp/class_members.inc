<?php

namespace Lib;

require __DIR__ . '/harness/Registry.inc';
require __DIR__ . '/Members.sharp';
require __DIR__ . '/MembersErrors.sharp';

use Demo\Members;
use Demo\Misread;

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

foreach ([[Misread::class, 'missing'], [Misread::class, 'hidden'], [Members::class, 'late']] as [$class, $method]) {
    try {
        $class::$method();
    } catch (\Error $e) {
        echo $e->getMessage(), "\n";
    }
}
