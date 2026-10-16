<?php

require __DIR__ . '/Ids.sharp';

use Holders\Ids;
use Holders\Ticket;

$ticket = new Ticket();
$plain = new stdClass();

var_dump(Ids::identity($ticket) === spl_object_id($ticket));
var_dump(Ids::either(null, $plain) === $plain);
var_dump(Ids::either($ticket, $plain) === $ticket);
echo Ids::describe($ticket), "\n";
echo Ids::describe($plain), "\n";

try {
    Ids::identity(5);
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

try {
    Ids::either(null, null);
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}
