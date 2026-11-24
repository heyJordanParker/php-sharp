--TEST--
A module's bindings are code of its module's class, so each builds its namespace's internal classes, as does each lambda it builds wherever it runs, in a base, an environment and a test module, and code of that class still can't build another namespace's internal class
--ENV--
SHARP_ENV=Production
--FILE--
<?php
require __DIR__ . '/Container.inc';

$root = container_project('module-binding-internal', [
    'app/Store/Module.sharp' => <<<'SHARP'
namespace App.Store;

import Sharp.Time.Clock;

module
{
    singleton Ledger(Clock clock) = new Ledger(clock);
    singleton Holder = new Holder(() => new Counter().total());
}
SHARP,
    'app/Store/Module.Production.sharp' => <<<'SHARP'
namespace App.Store;

module : App.Store
{
    transient Tally = new Tally(new Counter().total());
}
SHARP,
    'app/Store/Ledger.sharp' => <<<'SHARP'
namespace App.Store;

import Sharp.Time.Clock;

internal final class Ledger
{
    internal Ledger(Clock clock) { }
}
SHARP,
    'app/Store/Counter.sharp' => <<<'SHARP'
namespace App.Store;

internal final class Counter
{
    public int total() => 1;
}
SHARP,
    'app/Store/Tally.sharp' => <<<'SHARP'
namespace App.Store;

internal final class Tally
{
    public Tally(int count) { }
}
SHARP,
    'app/Store/Holder.sharp' => <<<'SHARP'
namespace App.Store;

public class Holder
{
    public Holder(private Function<int()> make) { }

    public int run() => this.make();
}
SHARP,
    'app/Billing/Invoice.sharp' => <<<'SHARP'
namespace App.Billing;

internal final class Invoice
{
}
SHARP,
    'tests/Store/LedgerTest.sharp' => <<<'SHARP'
namespace App.Store;

import PHPUnit.Framework.TestCase;

module : App.Store
{
    transient Counter = new Counter();
}

public class LedgerTest : TestCase
{
}
SHARP,
]);
require "$root/tests/Store/LedgerTest.sharp";
$container = new Sharp\Container();

function attempt(string $label, Closure $run): void
{
    try {
        $result = $run();
    } catch (Error $error) {
        $result = get_class($error) . ": " . $error->getMessage();
    }
    echo $label, ": ", $result, "\n";
}

attempt('the base module builds an internal class', fn () => get_class($container->get('App\Store\Ledger')));
attempt('a lambda a binding builds, called by a method', fn () => $container->get('App\Store\Holder')->run());
attempt('the environment module builds an internal class', fn () => get_class($container->get('App\Store\Tally')));
Sharp\Internal\startTest('App\Store\LedgerTest');
attempt('the test module builds an internal class', fn () => get_class($container->get('App\Store\Counter')));
Sharp\Internal\endTest();
// The checker refuses a binding that names another namespace's internal class, so plain PHP runs code of App.Store's
// module class, as each of its bindings is, to show the engine refuses it too.
attempt(
    'code of the module class builds an internal class of another namespace',
    fn () => get_class(Closure::bind(static fn () => new App\Billing\Invoice(), null, 'App\Store\Module')()),
);
remove_project($root);
?>
--EXPECT--
the base module builds an internal class: App\Store\Ledger
a lambda a binding builds, called by a method: 1
the environment module builds an internal class: App\Store\Tally
the test module builds an internal class: App\Store\Counter
code of the module class builds an internal class of another namespace: Error: Cannot instantiate internal class App\Billing\Invoice from App\Store\Module, outside namespace App\Billing
