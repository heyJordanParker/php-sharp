--TEST--
PHP# implements a generic interface with the type argument's type, and a call from plain PHP is checked against the written type
--FILE--
<?php

require __DIR__ . '/Handlers.sharp';

// PHP# calls `handle` through the interface, and plain PHP calls it on the class.
$handler = new Handlers\OrderHandler();
Handlers\Dispatch::send($handler, 4);
$handler->handle(new Handlers\Order(3));
echo $handler->handled, "\n";

// The interface erases `item` to any value, and the class written for `Order` still refuses a string.
try {
    $handler->handle("x");
} catch (TypeError $error) {
    echo $error->getMessage(), "\n";
}
echo $handler instanceof Handlers\Handler ? "a Handler\n" : "no Handler\n";

// An override of a class's method is widened as an interface's implementation is.
$inbox = new Handlers\OrderInbox();
echo Handlers\Dispatch::tally($inbox, 5), ' ', $inbox->count(new Handlers\Order(6)), "\n";
try {
    $inbox->count(7);
} catch (TypeError $error) {
    echo $error->getMessage(), "\n";
}
?>
--EXPECTF--
7
Handlers\OrderHandler::handle(): Argument #1 ($item) must be of type Handlers.Order, string given, called in %s on line 13
a Handler
5 6
Handlers\OrderInbox::count(): Argument #1 ($item) must be of type Handlers.Order, int given, called in %s on line 23
