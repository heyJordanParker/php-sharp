<?php

namespace Psr\EventDispatcher;

// psr/event-dispatcher 1.0's interface, which Composer installs with sharp/composer.
interface ListenerProviderInterface
{
    public function getListenersForEvent(object $event): iterable;
}
