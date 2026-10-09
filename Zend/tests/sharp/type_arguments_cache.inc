<?php
require __DIR__ . '/type_arguments.inc';

use App\Queue;

// The arguments of the Box make() gives, and whether it spelled them: spelling reads a text into an arena, which raises
// the peak above what the Box keeps.
function made(App\Maker $maker): string
{
    memory_reset_peak_usage();
    $box = $maker->make();
    $spelled = memory_get_peak_usage() - memory_get_usage() > 512;

    return get_class($maker) . ': ' . implode(', ', arguments($box)) . ($spelled ? ' spelled' : ' cached');
}

// The site keeps only a list that lives for the process. Code spelling Maker<Order> makes App.Order one, whether this file
// was compiled now or read from the opcache file cache, where nothing compiles it.
Queue::maker();

// OrderMaker's header gives Maker<T> its argument, so the site in Maker.make() keeps what it spelled for OrderMaker. A
// Maker<int> misses it and spells again, and keeps its own. A ListMaker<int> spells a List<int> code never spells, which
// lives for the request, so it spells it on every call and keeps nothing, and OrderMaker's list stays.
foreach ([
    Queue::orderMaker(), Queue::orderMaker(), Queue::intMaker(), Queue::intMaker(),
    Queue::orderMaker(), Queue::listMaker(), Queue::listMaker(), Queue::orderMaker(),
] as $maker) {
    echo made($maker), "\n";
}
