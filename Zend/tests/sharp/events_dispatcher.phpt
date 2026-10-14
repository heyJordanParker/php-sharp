--TEST--
emit hands every event to the dispatcher a module binds for the class that emits it, with one function per listener: the standard library's runs them at once, and an app's or a test's may run them later, again, or never
--FILE--
<?php
require __DIR__ . '/Container.inc';

container_project('events-dispatcher', [
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
        }
        SHARP,
    'app/Events/Module.sharp' => <<<'SHARP'
        namespace App.Events;

        import Sharp.Dispatcher;

        module
        {
            singleton Dispatcher = Recorder;
        }
        SHARP,
    'app/Events/Recorder.sharp' => <<<'SHARP'
        namespace App.Events;

        import Sharp.Dispatcher;

        public class Recorder : Dispatcher
        {
            public static List<string> seen { get; set; } = [];

            public void dispatch(Any emitted, List<Function<void()>> listeners)
            {
                Recorder.seen.add(`${listeners.count()} listeners`);
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
    'app/Events/Later/Module.sharp' => <<<'SHARP'
        namespace App.Events.Later;

        import Sharp.Dispatcher;

        module
        {
            singleton Dispatcher = Later;
        }
        SHARP,
    'app/Events/Later/Later.sharp' => <<<'SHARP'
        namespace App.Events.Later;

        import Sharp.Dispatcher;

        public class Later : Dispatcher
        {
            public static List<Function<void()>> pending { get; set; } = [];

            public void dispatch(Any emitted, List<Function<void()>> listeners)
            {
                for (const listener of listeners)
                {
                    Later.pending.add(listener);
                }
            }

            public static void flush()
            {
                for (const listener of Later.pending)
                {
                    listener();
                }
                Later.pending = [];
            }
        }
        SHARP,
    'app/Events/Later/Payer.sharp' => <<<'SHARP'
        namespace App.Events.Later;

        import App.Events.Paid;

        public class Payer
        {
            public void pay(int orderId) { emit new Paid(orderId); }
        }
        SHARP,
    'app/Events/Retry/Module.sharp' => <<<'SHARP'
        namespace App.Events.Retry;

        import Sharp.Dispatcher;

        module
        {
            singleton Dispatcher = Retrying;
        }
        SHARP,
    'app/Events/Retry/Retrying.sharp' => <<<'SHARP'
        namespace App.Events.Retry;

        import Sharp.Dispatcher;
        import RuntimeException;

        public class Retrying : Dispatcher
        {
            public void dispatch(Any emitted, List<Function<void()>> listeners)
            {
                for (const listener of listeners)
                {
                    try {
                        listener();
                    } catch (RuntimeException failure) {
                        listener();
                    }
                }
            }
        }
        SHARP,
    'app/Events/Retry/Payer.sharp' => <<<'SHARP'
        namespace App.Events.Retry;

        import App.Events.Paid;

        public class Payer
        {
            public void pay(int orderId) { emit new Paid(orderId); }
        }
        SHARP,
]);

function heard(string $case, Closure $raise): void
{
    App\Events\Hears::$log = [];
    App\Events\Recorder::$seen = [];
    $raise();
    echo $case, ': ', implode(', ', App\Events\Hears::$log) ?: 'nothing ran', '; recorded: ', implode(', ', App\Events\Recorder::$seen) ?: 'nothing', "\n";
}

heard('plain PHP, the standard library\'s dispatcher', fn () => Sharp\Internal\emit(new App\Events\Paid(1)));
heard('App.Events binds a recorder', fn () => (new App\Events\Payer())->pay(2));
heard('the recorder sees an event no listener hears', fn () => (new App\Events\Payer())->ignore(3));
heard('App.Events.Later binds a dispatcher that runs them later', function (): void {
    (new App\Events\Later\Payer())->pay(4);
    echo 'before the flush: ', implode(', ', App\Events\Hears::$log) ?: 'nothing ran', "\n";
    App\Events\Later\Later::flush();
});
heard('App.Events.Retry binds one that retries a listener once', function (): void {
    App\Events\Hears::$failures = 1;
    (new App\Events\Retry\Payer())->pay(5);
});

Sharp\Internal\testModule(['Tests\PayerTest'], [['app\events', 'sharp\dispatcher', null, 'singleton', 'Sharp\ImmediateDispatcher']]);
Sharp\Internal\startTest('Tests\PayerTest');
heard('a test module replaces App.Events\'s recorder', fn () => (new App\Events\Payer())->pay(6));
Sharp\Internal\endTest();
heard('after the test, the recorder again', fn () => (new App\Events\Payer())->pay(7));
?>
--EXPECT--
plain PHP, the standard library's dispatcher: flaky 1, hear 1; recorded: nothing
App.Events binds a recorder: nothing ran; recorded: 2 listeners
the recorder sees an event no listener hears: nothing ran; recorded: 0 listeners
before the flush: nothing ran
App.Events.Later binds a dispatcher that runs them later: flaky 4, hear 4; recorded: nothing
App.Events.Retry binds one that retries a listener once: flaky 5, hear 5; recorded: nothing
a test module replaces App.Events's recorder: flaky 6, hear 6; recorded: nothing
after the test, the recorder again: nothing ran; recorded: 2 listeners
