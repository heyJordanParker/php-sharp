<?php

use GenericEvents\Hears;
use GenericEvents\Invoice;
use GenericEvents\Order;

// Prints what the listeners heard by the time the raise returned.
function heard(string $case, Closure $raise): void
{
    Hears::$log = [];
    $raise();
    echo $case, ': ', implode(', ', Hears::$log) ?: 'nothing', "\n";
}

// Each emit runs twice, so the JIT runs compile the listeners before the second run.
for ($run = 0; $run < 2; $run++) {
    heard('emit new Saved<Order>', fn () => (new Order(7))->save());
    heard('emit new Saved<Invoice>', fn () => (new Invoice(8))->save());
    $listening = Hears::watchOrders();
    heard('Events.on<Saved<Order>> and a Saved<Order>', fn () => (new Order(9))->save());
    heard('Events.on<Saved<Order>> and a Saved<Invoice>', fn () => (new Invoice(10))->save());
    $listening = null;
    heard('plain PHP emits a Saved<Invoice>', fn () => Sharp\Internal\emit(Hears::invoiceSaved(11), 'Sharp\Dispatcher'));
    $listeners = (new Sharp\Psr\ListenerProvider())->getListenersForEvent(Hears::invoiceSaved(12));
    heard('a PSR-14 dispatcher runs the listeners of a Saved<Invoice>', function () use ($listeners): void {
        foreach ($listeners as $listener) {
            $listener(Hears::invoiceSaved(12));
        }
    });
}
