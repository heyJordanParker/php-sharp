--TEST--
The engine holds an internal member, an internal class's `new` and each public member of an internal class that replaces no wider one, to its namespace and the namespaces below it, spec section 5: plain PHP and a PHP# call the checker can't follow throw Error from outside, through a call, a property read or write, a write through an internal set, a constant, `new`, a callable string or array, call_user_func and Closure::fromCallable, and get_object_vars, var_export and an array cast that plain PHP makes leave an internal property out, while json_encode writes the class's stored form, internal fields included, while plain PHP and PHP# of the namespace, PHP# of one below it and of a test folder that declares it reach every member, and Json.encode lists every one
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/harness/Dispatch.inc';

use Demo\InternalReach\Below\Audit;
use Demo\InternalReach\Dispatch;
use Demo\InternalReach\Inside;
use Demo\InternalReach\Ledger;
use Demo\InternalReach\Probe;
use Demo\Reaching\Caller;

function attempt(string $label, Closure $body): void
{
    echo $label, ": ";
    try {
        echo json_encode($body()), "\n";
    } catch (Throwable $error) {
        echo get_class($error), ": ", $error->getMessage(), "\n";
    }
}

$ledger = Ledger::open();
$till = Ledger::till();

attempt('call', fn () => $ledger->total());
attempt('static call', fn () => Ledger::doubled(2));
attempt('first-class callable', fn () => $ledger->total(...));
attempt('read', fn () => $ledger->balance);
attempt('write', function () use ($ledger) { $ledger->balance = 3; });
attempt('write through an internal set', function () use ($ledger) { $ledger->views = 3; });
attempt('add to it through an internal set', function () use ($ledger) { $ledger->views++; });
attempt('constant', fn () => Ledger::LIMIT);
attempt('new', fn () => new Ledger());
attempt('new of an internal class', fn () => new Demo\InternalReach\Till());
attempt('public method of an internal class', fn () => $till->counted());
attempt('public property of an internal class', fn () => $till->count);
attempt('its method through a public interface', fn () => $till->label());
attempt('callable string', fn () => 'Demo\InternalReach\Ledger::doubled'(2));
attempt('callable array', fn () => [$ledger, 'total']());
attempt('call_user_func string', fn () => call_user_func('Demo\InternalReach\Ledger::doubled', 2));
attempt('call_user_func array', fn () => call_user_func([$ledger, 'total']));
attempt('Closure::fromCallable', fn () => Closure::fromCallable([$ledger, 'total']));
attempt('PHP# by name', fn () => Caller::byName());
attempt('PHP# by array', fn () => Caller::byArray($ledger));
attempt('plain PHP of its namespace by name', fn () => Dispatch::call('Demo\InternalReach\Ledger::doubled', 2));
attempt('plain PHP of its namespace by array', fn () => Dispatch::call([$ledger, 'total']));
attempt('plain PHP of its namespace reads', fn () => Dispatch::read($ledger, 'balance'));
attempt('plain PHP of its namespace writes through an internal set', fn () => Dispatch::write($ledger, 'views', 2));
attempt('its namespace', fn () => Inside::uses());
attempt('below it', fn () => Audit::uses());
attempt('a test folder', fn () => Probe::uses());

echo "listed by plain PHP outside its namespace:\n", implode("\n", Demo\Reaching\Dispatch::listed(Ledger::open())), "\n";
echo "listed by plain PHP of its namespace:\n", implode("\n", Dispatch::listed(Ledger::open())), "\n";
echo "Json.encode outside: ", Sharp\Encoding\Json::encode(Ledger::open()), "\n";
?>
--EXPECT--
call: Error: Call to internal method Demo\InternalReach\Ledger::total() from global scope, outside namespace Demo\InternalReach
static call: Error: Call to internal method Demo\InternalReach\Ledger::doubled() from global scope, outside namespace Demo\InternalReach
first-class callable: Error: Call to internal method Demo\InternalReach\Ledger::total() from global scope, outside namespace Demo\InternalReach
read: Error: Cannot access internal property Demo\InternalReach\Ledger::$balance from global scope, outside namespace Demo\InternalReach
write: Error: Cannot access internal property Demo\InternalReach\Ledger::$balance from global scope, outside namespace Demo\InternalReach
write through an internal set: Error: Cannot modify internal(set) property Demo\InternalReach\Ledger::$views from global scope, outside namespace Demo\InternalReach
add to it through an internal set: Error: Cannot modify internal(set) property Demo\InternalReach\Ledger::$views from global scope, outside namespace Demo\InternalReach
constant: Error: Cannot access internal constant Demo\InternalReach\Ledger::LIMIT from global scope, outside namespace Demo\InternalReach
new: Error: Call to internal Demo\InternalReach\Ledger::__construct() from global scope, outside namespace Demo\InternalReach
new of an internal class: Error: Cannot instantiate internal class Demo\InternalReach\Till from global scope, outside namespace Demo\InternalReach
public method of an internal class: Error: Call to internal method Demo\InternalReach\Till::counted() from global scope, outside namespace Demo\InternalReach
public property of an internal class: Error: Cannot access internal property Demo\InternalReach\Till::$count from global scope, outside namespace Demo\InternalReach
its method through a public interface: "till"
callable string: Error: Call to internal method Demo\InternalReach\Ledger::doubled() from global scope, outside namespace Demo\InternalReach
callable array: Error: Call to internal method Demo\InternalReach\Ledger::total() from global scope, outside namespace Demo\InternalReach
call_user_func string: TypeError: call_user_func(): Argument #1 ($callback) must be a valid callback, cannot access internal method Demo\InternalReach\Ledger::doubled() from global scope, outside namespace Demo\InternalReach
call_user_func array: TypeError: call_user_func(): Argument #1 ($callback) must be a valid callback, cannot access internal method Demo\InternalReach\Ledger::total() from global scope, outside namespace Demo\InternalReach
Closure::fromCallable: TypeError: Failed to create closure from callable: cannot access internal method Demo\InternalReach\Ledger::total() from global scope, outside namespace Demo\InternalReach
PHP# by name: TypeError: call_user_func(): Argument #1 ($callback) must be a valid callback, cannot access internal method Demo\InternalReach\Ledger::doubled() from Demo\Reaching\Dispatch, outside namespace Demo\InternalReach
PHP# by array: TypeError: call_user_func(): Argument #1 ($callback) must be a valid callback, cannot access internal method Demo\InternalReach\Ledger::total() from Demo\Reaching\Dispatch, outside namespace Demo\InternalReach
plain PHP of its namespace by name: 4
plain PHP of its namespace by array: 10
plain PHP of its namespace reads: 5
plain PHP of its namespace writes through an internal set: 2
its namespace: [12,6,1,10,4,3]
below it: [14,7,2,10,8]
a test folder: [16,8,10,12]
listed by plain PHP outside its namespace:
{"views":0}
{"views":0}
{"balance":5,"views":0}
\Demo\InternalReach\Ledger::__set_state(array(
   'views' => 0,
))
{"views":0}
listed by plain PHP of its namespace:
{"balance":5,"views":0}
{"balance":5,"views":0}
{"balance":5,"views":0}
\Demo\InternalReach\Ledger::__set_state(array(
   'balance' => 5,
   'views' => 0,
))
{"balance":5,"views":0}
Json.encode outside: {"balance":5,"views":0}
