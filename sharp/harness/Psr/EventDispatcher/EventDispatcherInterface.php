<?php

namespace Psr\EventDispatcher;

// psr/event-dispatcher 1.0's interface, which Composer installs with sharp/composer.
interface EventDispatcherInterface
{
    public function dispatch(object $event);
}
