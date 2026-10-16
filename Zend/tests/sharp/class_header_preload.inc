<?php

require __DIR__ . '/harness/Header.inc';
require __DIR__ . '/harness/Traits.inc';
require_once __DIR__ . '/harness/fixture.inc';

// Preloading keeps the classes its script declares, so the script loads every file of each fixture.
foreach (['Header', 'Traits', 'Inheritance'] as $fixture) {
    fixture($fixture);
    foreach (glob(__DIR__ . "/$fixture/*.sharp") as $file) {
        require_once $file;
    }
}
