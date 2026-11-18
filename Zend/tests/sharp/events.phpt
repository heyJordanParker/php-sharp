--TEST--
emit runs every listener of an event before it returns, in table order, each once, and an exception from one stops the rest
--FILE--
<?php

require __DIR__ . '/vendor/autoload.php';

final class Unheard {}

// Prints what the listeners heard by the time the raise returned, and the exception that left it.
function heard(Closure $raise): void
{
    Shop\OrderNotices::$heard = [];
    try {
        $raise();
    } catch (InvalidArgumentException $exception) {
        echo $exception->getMessage(), ', after ';
    }
    echo implode(', ', Shop\OrderNotices::$heard) ?: 'nothing', "\n";
}

// Each emit runs twice, so the JIT variants compile the listeners before the second run.
$register = new Shop\Register();
for ($run = 0; $run < 2; $run++) {
    heard(fn () => $register->pay(7, 5));
    heard(fn () => $register->refund(8));
    heard(fn () => $register->pay(9, -1));
    heard(fn () => Sharp\Internal\emit(new Shop\OrderPaid(10, 3), 'Sharp\Dispatcher'));
    heard(fn () => Sharp\Internal\emit(new Symfony\Contracts\EventDispatcher\Event(), 'Sharp\Dispatcher'));
    heard(fn () => Sharp\Internal\emit(new Unheard(), 'Sharp\Dispatcher'));
}

try {
    Sharp\Internal\emit(7, 'Sharp\Dispatcher');
} catch (TypeError $error) {
    echo $error->getMessage(), "\n";
}
$paid = new Shop\OrderPaid(11, 4);
try {
    $paid->amount = 0;
} catch (Error $error) {
    echo $error->getMessage(), "\n";
}
?>
--EXPECT--
send 7, refresh, audit 7, deliver 7, ship 7
send 8, audit 8, reissue 8, send 108, refresh, audit 108, deliver 108, ship 108, restock 8
order 9 has a negative amount, after send 9, refresh, audit 9
send 10, refresh, audit 10, deliver 10, ship 10
alert false
nothing
send 7, refresh, audit 7, deliver 7, ship 7
send 8, audit 8, reissue 8, send 108, refresh, audit 108, deliver 108, ship 108, restock 8
order 9 has a negative amount, after send 9, refresh, audit 9
send 10, refresh, audit 10, deliver 10, ship 10
alert false
nothing
Sharp\Internal\emit(): Argument #1 ($event) must be of type object, int given
Cannot modify readonly property Shop\OrderPaid::$amount
