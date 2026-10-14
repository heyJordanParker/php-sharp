--TEST--
Sharp\Psr\ListenerProvider gives a plain PHP library's PSR-14 dispatcher every listener of the events it dispatches: an extern event and a PHP# event reach each on method on a new object, then each scoped listener, in emit's order, and an event no listener hears gets none
--FILE--
<?php
namespace Psr\EventDispatcher {
    // psr/event-dispatcher 1.0's interfaces, which Composer loads in a project.
    interface EventDispatcherInterface
    {
        public function dispatch(object $event);
    }

    interface ListenerProviderInterface
    {
        public function getListenersForEvent(object $event): iterable;
    }
}

namespace Lib {
    // A plain PHP library's own dispatcher, which follows PSR-14 and knows nothing of PHP#.
    final class Dispatcher implements \Psr\EventDispatcher\EventDispatcherInterface
    {
        public function __construct(private \Psr\EventDispatcher\ListenerProviderInterface $provider) {}

        public function dispatch(object $event): object
        {
            foreach ($this->provider->getListenersForEvent($event) as $listener) {
                $listener($event);
            }

            return $event;
        }
    }
}

namespace {
    require dirname(__DIR__, 3) . '/sharp/composer/src/Psr/ListenerProvider.php';
    require __DIR__ . '/harness/JobFailed.inc';
    require __DIR__ . '/OrderEvent.sharp';
    require __DIR__ . '/OrderPaid.sharp';
    require __DIR__ . '/OrderNotices.sharp';
    require __DIR__ . '/Receipts.sharp';
    require __DIR__ . '/Shipping.sharp';

    final class Unheard {}

    $dispatcher = new Lib\Dispatcher(new Sharp\Psr\ListenerProvider());

    function heard(string $case, object $event): void
    {
        global $dispatcher;
        Shop\OrderNotices::$heard = [];
        $dispatcher->dispatch($event);
        echo $case, ': ', implode(', ', Shop\OrderNotices::$heard) ?: 'nothing', "\n";
    }

    heard('an extern event', new Lib\JobFailed('mail'));
    heard('a PHP# event', new Shop\OrderPaid(1, 5));
    [$first] = Shop\Shipping::$made;
    heard('again', new Shop\OrderPaid(2, 5));
    echo 'a new object for every event: ', var_export($first !== Shop\Shipping::$made[1], true), "\n";
    $listening = Sharp\Internal\listen('Shop\OrderEvent', function (object $paid): void {
        Shop\OrderNotices::$heard[] = "scoped $paid->orderId";
    });
    heard('a scoped listener, after the table', new Shop\OrderPaid(3, 5));
    $listening = null;
    heard('after its handle is gone', new Shop\OrderPaid(4, 5));
    echo 'listeners of an event no listener hears: ', count([...(new Sharp\Psr\ListenerProvider())->getListenersForEvent(new Unheard())]), "\n";
}
?>
--EXPECT--
an extern event: alert mail
a PHP# event: send 1, refresh, audit 1, deliver 1, ship 1
again: send 2, refresh, audit 2, deliver 2, ship 2
a new object for every event: true
a scoped listener, after the table: send 3, refresh, audit 3, deliver 3, ship 3, scoped 3
after its handle is gone: send 4, refresh, audit 4, deliver 4, ship 4
listeners of an event no listener hears: 0
