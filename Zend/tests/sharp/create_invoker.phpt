--TEST--
Method.createInvoker makes a value of a function type that calls the method with a list of arguments, so a dispatcher under `uses Database` calls methods of their own parameters through one invoker each: a parameter the list leaves out takes its default, a value that doesn't fit its parameter throws TypeError naming it, and a method whose effects the type doesn't list, a default that runs code, a missing required value, a value past the last parameter and a type that passes no list are refused, and a property's accessor takes its value from the list as a method does
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use App\Invokers\Billing;
use App\Invokers\Dispatch;
use App\Invokers\FakeDatabase;
use App\Invokers\FakeMailer;
use App\Invokers\Plan;
use App\Invokers\Stamp;
use App\Invokers\Subscription;
use App\Invokers\VerifiedUser;

function attempt(string $label, Closure $body): void
{
    echo $label, ": ";
    try {
        var_export($body());
        echo "\n";
    } catch (Throwable $error) {
        echo get_class($error), ": ", $error->getMessage(), "\n";
    }
}

$billing = new Billing(new FakeDatabase(), new FakeMailer());
$dispatch = new Dispatch();
$subscription = new Subscription('Gold');
$user = new VerifiedUser('Ann');

attempt('pause', fn () => $dispatch->pause($billing, [$subscription, $user]));
attempt('create with its default', fn () => $dispatch->create(['Acme']));
attempt('create with a plan', fn () => $dispatch->create(['Acme', Plan::Pro]));
attempt('pause with a wrong value', fn () => $dispatch->pause($billing, ['Gold', $user]));
attempt('pause with a missing value', fn () => $dispatch->pause($billing, [$subscription]));
attempt('create with a value too many', fn () => $dispatch->create(['Acme', Plan::Pro, 3]));
attempt('notify under Database', fn () => $dispatch->notifier());
attempt('stamp with a value', fn () => $dispatch->stamped([new Stamp()]));
attempt('stamp with its default', fn () => $dispatch->stamped([]));
attempt('a type that passes no list', fn () => $dispatch->spread());
attempt('write note', fn () => $dispatch->write($billing, ['paid']));
attempt('read note', fn () => $dispatch->read($billing, []));
attempt('write note with no value', fn () => $dispatch->write($billing, []));
attempt('read note with a value', fn () => $dispatch->read($billing, ['x']));
?>
--EXPECT--
pause: 'Gold paused by Ann in 3 rows'
create with its default: 'Acme on Normal'
create with a plan: 'Acme on Pro'
pause with a wrong value: TypeError: App\Invokers\Billing::pause(): Argument #1 ($subscription) must be of type App\Invokers\Subscription, string given
pause with a missing value: ArgumentCountError: `App.Invokers.Billing.pause` takes `user`, and was given no value for it.
create with a value too many: ArgumentCountError: `App.Invokers.Billing.create` takes at most 2 arguments, and was given 3.
notify under Database: ValueError: `App.Invokers.Billing.notify` has the effect `App.Invokers.Mailer`, and `Function<Any?(Object, List<Any?>) uses App.Invokers.Database>` allows only `App.Invokers.Database`.
stamp with a value: 'now'
stamp with its default: ValueError: `App.Invokers.Billing.stamp` gives `given` a default that runs code, which an invoker never runs, and was given no value for it.
a type that passes no list: ValueError: `App.Invokers.Billing.pause` takes its arguments as one `List<Any?>` after its receiver, and `Function<Any?(Object, App.Invokers.Subscription, App.Invokers.VerifiedUser) uses App.Invokers.Database>` doesn't.
write note: NULL
read note: 'paid'
write note with no value: ArgumentCountError: `App.Invokers.Billing.note.set` takes `value`, and was given no value for it.
read note with a value: ArgumentCountError: `App.Invokers.Billing.note.get` takes at most 0 arguments, and was given 1.
