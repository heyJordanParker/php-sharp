<?php

// The preload request runs a check of an out type argument and of a backed enum key, so each loads its class while
// opcache preloads, and keeps it. A failed check throws, and the preload fails. No function type is checked here: a
// closure a method makes during the preload request trips an assertion in the function JIT, in plain PHP too.
require __DIR__ . '/type_checks_autoload.inc';
Orchard\Picker::weigh(Orchard\Picker::apples());
$stock = ['ripe' => 3];
$stall = new Orchard\Stall();
$stall->stock = $stock;
