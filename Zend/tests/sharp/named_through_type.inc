<?php

use NamedThroughType\Invoice;
use NamedThroughType\Record;
use NamedThroughType\Repository;

// Each row runs twice, so the JIT runs compile the methods before the second run.
for ($run = 0; $run < 2; $run++) {
    echo 'new TItem.fromJson for Repository<Invoice> and Repository<Record>: ', Repository::run(), "\n";
    $made = Invoice::fromJson(['id' => '9']);
    echo 'plain PHP calls the named constructor it runs as: ', $made::class, ' ', $made->id, "\n";
    echo 'a bound below the class that requires fromJson: ', Repository::below(), "\n";
    $parsed = Invoice::parse(['id' => '5']);
    $copied = $parsed->copy();
    echo 'new Self.fromJson on the class the code is called on: ', $parsed::class, ' ', $parsed->id, ', ', $copied::class, ' ', $copied->id, ', ', Record::parse(['id' => '6'])->id, "\n";
}
