<?php

require __DIR__ . '/harness/Header.inc';
require __DIR__ . '/harness/Traits.inc';
require __DIR__ . '/vendor/autoload.php';

// Preloading keeps the classes its script declares, so the script loads every file of each fixture, and, as the
// preload mago compile writes does, the trait of the anonymous classes Composer's up-front files declare.
trait_exists(Illuminate\Support\Traits\ReflectsClosures::class);
foreach (['Header', 'Traits', 'Inheritance'] as $fixture) {
    foreach (glob(__DIR__ . "/$fixture/*.sharp") as $file) {
        require_once $file;
    }
}
