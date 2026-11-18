<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/harness/JobFailed.inc';

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
    heard(fn () => Sharp\Internal\emit(new Lib\JobFailed('mail'), 'Sharp\Dispatcher'));
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
