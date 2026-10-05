<?php

namespace Lib;

enum Order: string
{
    case Ascending = 'asc';
    case Descending = 'desc';
}

final class Registry
{
    public const int VERSION = 2;
    public static string $label = 'registry';
    protected static int $hidden = 1;
}

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

Registry::$label = 'changed';
var_dump(Members::label());

foreach (['missing', 'hidden'] as $method) {
    try {
        Members::$method();
    } catch (\Error $e) {
        echo $e->getMessage(), "\n";
    }
}
