--TEST--
Sharp\Container builds only the classes a module binds. It throws its own failures as Sharp\ContainerError: a service no module binds, a concrete class no module binds, a class that needs itself, a parameter with no class type, and a bound class that isn't there. It throws a constructor's exception unchanged and the request goes on, and a module that won't load is refused each time it is read
--FILE--
<?php
require __DIR__ . '/Container.inc';

class Egg
{
    public function __construct(Chicken $chicken) {}
}

class Chicken
{
    public function __construct(Egg $egg) {}
}

class Loose
{
    public function __construct($anything) {}
}

class Ledger
{
    public function __construct()
    {
        throw new RuntimeException('the database is down');
    }
}

class Guard
{
    public function __destruct()
    {
        throw new RuntimeException('the guard failed');
    }
}

class Receipt
{
}

$root = container_project('container-refusals');
$container = new Sharp\Container();

// A test's module, as the compiler writes one, binds these classes. The checker refuses the first four bindings.
Sharp\Internal\testModule('RefusalsTest', [
    ['', 'egg', null, 'transient', 'Egg'],
    ['', 'chicken', null, 'transient', 'Chicken'],
    ['', 'loose', null, 'transient', 'Loose'],
    ['', 'ghost', null, 'transient', 'Ghost'],
    ['', 'ledger', null, 'transient', 'Ledger'],
    ['', 'ledgers', null, 'transient', ['Ledger']],
    ['', 'openledger', null, 'transient', fn() => new Ledger()],
    // It returns a Receipt, and then its local's destructor throws.
    ['', 'guardedreceipt', null, 'transient', function () {
        $guard = new Guard();
        return new Receipt();
    }],
]);
Sharp\Internal\startTest('RefusalsTest');

foreach (['App\Mail\Mailer', 'App\Missing', 'App\Mail\SmtpMailer', 'Egg', 'Loose', 'Ghost', 'Ledger', 'Ledgers', 'OpenLedger', 'GuardedReceipt'] as $id) {
    try {
        $container->get($id);
    } catch (Throwable $e) {
        echo $id, ": ", get_class($e), ": ", $e->getMessage(), "\n";
    }
}
var_dump($container->has('App\Missing'), $container->has('App\Mail\SmtpMailer'), $container->has('Egg'));
Sharp\Internal\endTest();

// A module edited since its compile is refused each time the container reads it, never skipped.
file_put_contents("$root/app/Shop/Admin/Module.sharp", CONTAINER_APP['app/Shop/Admin/Module.sharp'] . "\n");
foreach ([1, 2] as $attempt) {
    try {
        $container->get('App\Shop\Admin\Panel');
    } catch (Throwable $e) {
        echo "Panel, attempt $attempt: ", get_class($e), ": ", $e->getMessage(), "\n";
    }
}
echo "the request goes on\n";
?>
--EXPECT--
App\Mail\Mailer: Sharp\ContainerError: No module binds App\Mail\Mailer
App\Missing: Sharp\ContainerError: No module binds App\Missing
App\Mail\SmtpMailer: Sharp\ContainerError: No module binds App\Mail\SmtpMailer
Egg: Sharp\ContainerError: Egg needs itself, so the container can't build it
Loose: Sharp\ContainerError: The container can't fill parameter $anything of Loose: give it a class type
Ghost: Sharp\ContainerError: Class "Ghost" not found
Ledger: RuntimeException: the database is down
Ledgers: RuntimeException: the database is down
OpenLedger: RuntimeException: the database is down
GuardedReceipt: RuntimeException: the guard failed
bool(false)
bool(false)
bool(true)
Panel, attempt 1: CompileError: app/Shop/Admin/Module.sharp is out of date (app/Shop/Admin/Module.sharp changed). Run vendor/bin/mago compile.
Panel, attempt 2: CompileError: app/Shop/Admin/Module.sharp is out of date (app/Shop/Admin/Module.sharp changed). Run vendor/bin/mago compile.
the request goes on
