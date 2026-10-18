<?php

use NamedThroughType\Invoice;
use NamedThroughType\Repository;

// Each row runs twice, so the JIT runs compile the methods before the second run.
for ($run = 0; $run < 2; $run++) {
    echo 'new TItem.fromJson for Repository<Invoice> and Repository<Record>: ', Repository::run(), "\n";
    $made = Invoice::fromJson(['id' => '9']);
    echo 'plain PHP calls the named constructor it runs as: ', $made::class, ' ', $made->id, "\n";
}
