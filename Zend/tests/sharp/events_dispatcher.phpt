--TEST--
emit hands every event to the app's dispatcher, which the app's top module binds, with one function per listener, whoever emits it: the standard library's runs them at once, and an app's or a test's may run them later, again, or never
--FILE--
<?php
require __DIR__ . '/Container.inc';

$root = container_project('events-dispatcher', [
    'app/Module.sharp' => <<<'SHARP'
        namespace App;

        import Sharp.Dispatcher;

        module
        {
            singleton Dispatcher = Recorder;
        }
        SHARP,
    'app/Recorder.sharp' => <<<'SHARP'
        namespace App;

        import Sharp.Dispatcher;

        public class Recorder : Dispatcher
        {
            public static List<string> seen { get; set; } = [];

            public void dispatch(Any emitted, List<Function<void(Any)>> listeners)
            {
                Recorder.seen.add(`${listeners.count()} listeners`);
            }
        }
        SHARP,
    'app/Events/Paid.sharp' => <<<'SHARP'
        namespace App.Events;

        public event Paid(int orderId);
        SHARP,
    'app/Events/Unheard.sharp' => <<<'SHARP'
        namespace App.Events;

        public event Unheard(int orderId);
        SHARP,
    'app/Events/Hears.sharp' => <<<'SHARP'
        namespace App.Events;

        import Acme.Billing.Invoiced;
        import RuntimeException;

        public class Hears
        {
            public static List<string> log { get; set; } = [];
            public static int failures { get; set; } = 0;

            public void flaky(Paid e) on Paid
            {
                if (Hears.failures > 0) {
                    Hears.failures -= 1;
                    throw new RuntimeException(`flaky failed on ${e.orderId}`);
                }
                Hears.log.add(`flaky ${e.orderId}`);
            }

            public void hear(Paid e) on Paid
            {
                Hears.log.add(`hear ${e.orderId}`);
            }

            public void invoiced(Invoiced e) on Invoiced
            {
                Hears.log.add(`invoiced ${e.invoiceId}`);
            }
        }
        SHARP,
    'app/Events/Payer.sharp' => <<<'SHARP'
        namespace App.Events;

        public class Payer
        {
            public void pay(int orderId) { emit new Paid(orderId); }

            public void ignore(int orderId) { emit new Unheard(orderId); }
        }
        SHARP,
    'app/Testing/Later.sharp' => <<<'SHARP'
        namespace App.Testing;

        import Sharp.Dispatcher;

        public class Later : Dispatcher
        {
            public static List<Function<void()>> pending { get; set; } = [];

            public void dispatch(Any emitted, List<Function<void(Any)>> listeners)
            {
                for (const listener of listeners)
                {
                    Later.pending.add(() => listener(emitted));
                }
            }

            public static void flush()
            {
                for (const run of Later.pending)
                {
                    run();
                }
                Later.pending = [];
            }
        }
        SHARP,
    'app/Testing/Retrying.sharp' => <<<'SHARP'
        namespace App.Testing;

        import Sharp.Dispatcher;
        import RuntimeException;

        public class Retrying : Dispatcher
        {
            public void dispatch(Any emitted, List<Function<void(Any)>> listeners)
            {
                for (const listener of listeners)
                {
                    try {
                        listener(emitted);
                    } catch (RuntimeException failure) {
                        listener(emitted);
                    }
                }
            }
        }
        SHARP,
    'vendor/acme/billing/composer.json' => '{"name": "acme/billing"}',
    'vendor/acme/billing/src/Invoiced.sharp' => <<<'SHARP'
        namespace Acme.Billing;

        public event Invoiced(int invoiceId);
        SHARP,
    'vendor/acme/billing/src/Biller.sharp' => <<<'SHARP'
        namespace Acme.Billing;

        public class Biller
        {
            public void bill(int invoiceId) { emit new Invoiced(invoiceId); }
        }
        SHARP,
]);
spl_autoload_register(function (string $class) use ($root): void {
    $path = "$root/vendor/acme/billing/src/" . substr($class, strlen('Acme\\Billing\\')) . '.sharp';
    if (str_starts_with($class, 'Acme\\Billing\\') && is_file($path)) {
        require $path;
    }
});

function heard(string $case, Closure $raise): void
{
    App\Events\Hears::$log = [];
    App\Recorder::$seen = [];
    $raise();
    echo $case, ': ', implode(', ', App\Events\Hears::$log) ?: 'nothing ran', '; recorded: ', implode(', ', App\Recorder::$seen) ?: 'nothing', "\n";
}

heard('an app class emits', fn () => (new App\Events\Payer())->pay(1));
heard('the recorder sees an event no listener hears', fn () => (new App\Events\Payer())->ignore(2));
heard('plain PHP emits', fn () => Sharp\Internal\emit(new App\Events\Paid(3)));
heard('a PHP# package in vendor/ emits', fn () => (new Acme\Billing\Biller())->bill(4));

Sharp\Internal\testModule('Tests\LaterTest', [['app', 'sharp\dispatcher', null, 'singleton', 'App\Testing\Later']]);
Sharp\Internal\startTest('Tests\LaterTest');
heard('a test module binds a dispatcher that runs them later', function (): void {
    (new App\Events\Payer())->pay(5);
    (new Acme\Billing\Biller())->bill(6);
    echo 'before the flush: ', implode(', ', App\Events\Hears::$log) ?: 'nothing ran', "\n";
    App\Testing\Later::flush();
});
Sharp\Internal\endTest();

Sharp\Internal\testModule('Tests\Billing\RetryTest', [['tests\billing', 'sharp\dispatcher', null, 'singleton', 'App\Testing\Retrying']]);
Sharp\Internal\startTest('Tests\Billing\RetryTest');
heard('a test module of any namespace binds one that retries a listener once', function (): void {
    App\Events\Hears::$failures = 1;
    Sharp\Internal\emit(new App\Events\Paid(7));
});
Sharp\Internal\endTest();

heard('after the tests, the recorder again', fn () => (new Acme\Billing\Biller())->bill(8));
remove_project($root);
?>
--EXPECT--
an app class emits: nothing ran; recorded: 2 listeners
the recorder sees an event no listener hears: nothing ran; recorded: 0 listeners
plain PHP emits: nothing ran; recorded: 2 listeners
a PHP# package in vendor/ emits: nothing ran; recorded: 1 listeners
before the flush: nothing ran
a test module binds a dispatcher that runs them later: flaky 5, hear 5, invoiced 6; recorded: nothing
a test module of any namespace binds one that retries a listener once: flaky 7, hear 7; recorded: nothing
after the tests, the recorder again: nothing ran; recorded: 1 listeners
