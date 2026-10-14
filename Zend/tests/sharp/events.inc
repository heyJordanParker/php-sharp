<?php

// The standard library's module binds the dispatcher, as its autoloader reads it.
$library = dirname(__DIR__, 3) . '/sharp/composer/library';
Sharp\Internal\readModule("$library/Sharp/Module.sharp");
spl_autoload_register(static function (string $class) use ($library): void {
    $path = "$library/" . str_replace('\\', '/', $class) . '.sharp';
    if (str_starts_with($class, 'Sharp\\') && is_file($path)) {
        require $path;
    }
});

require __DIR__ . '/harness/JobFailed.inc';
require __DIR__ . '/OrderEvent.sharp';
require __DIR__ . '/OrderPaid.sharp';
require __DIR__ . '/OrderRefunded.sharp';
require __DIR__ . '/OrderNotices.sharp';
require __DIR__ . '/Receipts.sharp';
require __DIR__ . '/Shipping.sharp';
require __DIR__ . '/Register.sharp';

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
    heard(fn () => Sharp\Internal\emit(new Shop\OrderPaid(10, 3)));
    heard(fn () => Sharp\Internal\emit(new Lib\JobFailed('mail')));
    heard(fn () => Sharp\Internal\emit(new Unheard()));
}

try {
    Sharp\Internal\emit(7);
} catch (TypeError $error) {
    echo $error->getMessage(), "\n";
}
$paid = new Shop\OrderPaid(11, 4);
try {
    $paid->amount = 0;
} catch (Error $error) {
    echo $error->getMessage(), "\n";
}
