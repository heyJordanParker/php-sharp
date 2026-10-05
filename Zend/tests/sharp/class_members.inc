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

foreach (['missing', 'hidden'] as $method) {
    try {
        Members::$method();
    } catch (\Error $e) {
        echo $e->getMessage(), "\n";
    }
}
