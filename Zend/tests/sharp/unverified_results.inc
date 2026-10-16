<?php

namespace Plain;

require __DIR__ . '/Unverified.sharp';

use Crossed\Unverified;

final class NullSource implements \Crossed\Source
{
    public function read(): mixed
    {
        return null;
    }
}

$calls = [
    'Function<Any()> giving null' => fn () => Unverified::anything(fn () => null),
    'Function<Any()> giving 1' => fn () => Unverified::anything(fn () => 1),
    'Function<Object()> giving 5' => fn () => Unverified::object(fn () => 5),
    'Source.read() giving null' => fn () => Unverified::read(new NullSource()),
    'map' => fn () => Unverified::mapped(fn (int $n) => $n * 2),
    'sumOf' => fn () => Unverified::summed(fn (int $n) => $n * 2),
    'groupBy giving text' => fn () => Unverified::grouped(fn (int $n) => 'key'),
    'associateBy giving text' => fn () => Unverified::associated(fn (int $n) => 'key'),
    'lazy map' => fn () => Unverified::lazy(fn (int $n) => $n * 2),
    'map to Any giving null' => fn () => Unverified::anyMapped(fn (int $n) => null),
];
foreach ($calls as $name => $call) {
    try {
        $result = var_export($call(), true);
    } catch (\TypeError $error) {
        $result = $error->getMessage();
    }
    echo $name, ': ', str_replace("\n", ' ', $result), "\n";
}
