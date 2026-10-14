<?php

namespace Vendor\Events;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

// A plain PHP library's dispatcher, which follows PSR-14 and knows nothing of PHP#.
final class EventDispatcher implements EventDispatcherInterface
{
    public function __construct(private ListenerProviderInterface $provider)
    {
    }

    public function dispatch(object $event): object
    {
        foreach ($this->provider->getListenersForEvent($event) as $listener) {
            $listener($event);
        }

        return $event;
    }
}
