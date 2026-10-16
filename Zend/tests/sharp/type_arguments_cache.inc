<?php
require __DIR__ . '/type_arguments.inc';

use App\Queue;

// The arguments of the Box make() gives, and whether it spelled them: spelling reads a text into an arena of 1024 bytes,
// which raises the peak further than the Box does.
function made(App\Maker $maker): string
{
    memory_reset_peak_usage();
    $before = memory_get_usage();
    $box = $maker->make();
    $spelled = memory_get_peak_usage() - $before > 512;

    return get_class($maker) . ': ' . implode(', ', arguments($box)) . ($spelled ? ' spelled' : ' cached');
}

// Code spelling Maker<Order> makes App.Order a list that lives for the process, whether this file was compiled now or read
// from the opcache file cache, where nothing compiles it.
Queue::maker();

// OrderMaker's header gives Maker<T> its argument, so the site in Maker.make() keeps what it spelled for OrderMaker. A
// Maker<int> misses it and spells again, and keeps its own. A ListMaker<int> spells a List<int> code never spells, which
// lives for the request, and the site keeps it too, since its cache lives for the request. The request keeps every list
// the site spelled, so an OrderMaker after another class spells nothing again.
foreach ([
    Queue::orderMaker(), Queue::orderMaker(), Queue::intMaker(), Queue::intMaker(),
    Queue::orderMaker(), Queue::listMaker(), Queue::listMaker(), Queue::orderMaker(),
] as $maker) {
    echo made($maker), "\n";
}
