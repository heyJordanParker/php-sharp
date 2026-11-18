<?php

require __DIR__ . '/harness/Probe.inc';
require __DIR__ . '/vendor/autoload.php';

// Subscriber and Withholder extend Eloquent's Model, which the autoloader loads, and, as the preload mago compile
// writes does, the script loads the trait of the anonymous classes Composer's up-front files declare.
trait_exists(Illuminate\Support\Traits\ReflectsClosures::class);
require __DIR__ . '/vendor/heyjordanparker/php-sharp-composer/library/Sharp/ListMembers.sharp';
require __DIR__ . '/Crossings/Level.sharp';
require __DIR__ . '/Crossings/Weather.sharp';
require __DIR__ . '/Crossings/Crossings.sharp';
require __DIR__ . '/Crossings/Carton.sharp';
require __DIR__ . '/Crossings/Held.sharp';
require __DIR__ . '/Crossings/Subscriber.sharp';
require __DIR__ . '/Crossings/Withholder.sharp';
require __DIR__ . '/Crossings/Loosely.sharp';
require __DIR__ . '/collection_readers.inc';
