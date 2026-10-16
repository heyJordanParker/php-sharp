--TEST--
PHP# implements a generic interface with the type argument's type, a default body runs that implementation, and a call from plain PHP is checked against the written type
--FILE--
<?php

require __DIR__ . '/harness/fixture.inc';
fixture('Handlers');

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

// A default body runs the class's `handle`, and checks a call from plain PHP against the class's type argument.
$handler->handleTwice(new Handlers\Order(2));
echo $handler->handled, "\n";
try {
    $handler->handleTwice("x");
} catch (TypeError $error) {
    echo $error->getMessage(), "\n";
}

// An override of a class's method is widened as an interface's implementation is.
$inbox = new Handlers\OrderInbox();
echo Handlers\Dispatch::tally($inbox, 5), ' ', $inbox->count(new Handlers\Order(6)), "\n";
try {
    $inbox->count(7);
} catch (TypeError $error) {
    echo $error->getMessage(), "\n";
}

// Reflection reports the written type, as a framework that finds a listener's event from its parameter's type reads it.
$parameter = (new ReflectionMethod(Handlers\OrderHandler::class, 'handle'))->getParameters()[0];
echo $parameter->getType()->getName(), "\n";

// The check follows the caller's `strict_types`, as any typed parameter's does.
$echoes = new Handlers\Echoes();
$echoes->handle(5);
var_dump($echoes->last);
try {
    eval('declare(strict_types=1); $echoes->handle(6);');
} catch (TypeError $error) {
    echo $error->getMessage(), "\n";
}
?>
--EXPECTF--
7
Handlers\OrderHandler::handle(): Argument #1 ($item) must be of type Handlers\Order, string given, called in %s on line 14
a Handler
11
Handlers\OrderHandler::handleTwice(): Argument #1 ($item) must be of type Handlers.Order, string given, called in %s on line %d
5 6
Handlers\OrderInbox::count(): Argument #1 ($item) must be of type Handlers\Order, int given, called in %s on line %d
Handlers\Order
string(1) "5"
Handlers\Echoes::handle(): Argument #1 ($item) must be of type string, int given, called in %s : eval()'d code on line 1
