<?php

use SelfMembers\Counter;
use SelfMembers\OrderStore;
use SelfMembers\Store;

// Each row runs twice, so the JIT runs compile the methods before the second run.
for ($run = 0; $run < 2; $run++) {
    echo 'Self.TABLE called on the class and on its subclass: ', Store::table(), ' ', OrderStore::table(), "\n";
    echo 'Self.TABLE through an object: ', (new Store())->tableOfThis(), ' ', (new OrderStore())->tableOfThis(), "\n";
    echo 'Self.count written on each class: ', Store::bump(), ' ', OrderStore::bump(), "\n";
    echo 'plain PHP reads the statics: ', Store::$count, ' ', OrderStore::$count, "\n";
    echo 'Self.seen.add, which both classes share: ', Store::record(), ' ', OrderStore::record(), ' ', implode(',', Store::$seen), "\n";
    echo 'Self.count of each type argument: ', Counter::run(), "\n";
}
