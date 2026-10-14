<?php

require __DIR__ . '/Container.inc';

container_project('events-scoped', [
    'app/Scoped/OrderEvent.sharp' => <<<'SHARP'
        namespace App.Scoped;

        public interface OrderEvent
        {
            int orderId { get; }
        }
        SHARP,
    'app/Scoped/Paid.sharp' => <<<'SHARP'
        namespace App.Scoped;

        public event Paid(int orderId) : OrderEvent;
        SHARP,
    'app/Scoped/Notices.sharp' => <<<'SHARP'
        namespace App.Scoped;

        public class Notices
        {
            public static List<string> log { get; set; } = [];

            public void hear(Paid e) on Paid
            {
                Notices.log.add(`table ${e.orderId}`);
            }
        }
        SHARP,
    'app/Scoped/Later/Module.sharp' => <<<'SHARP'
        namespace App.Scoped.Later;

        import Sharp.Dispatcher;

        module
        {
            singleton Dispatcher = Later;
        }
        SHARP,
    'app/Scoped/Later/Later.sharp' => <<<'SHARP'
        namespace App.Scoped.Later;

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
    'app/Scoped/Later/Payer.sharp' => <<<'SHARP'
        namespace App.Scoped.Later;

        import App.Scoped.Paid;

        public class Payer
        {
            public void pay(int orderId) { emit new Paid(orderId); }
        }
        SHARP,
]);

// Starts a listener that logs its name and the order, on Paid or the type named.
function listening(string $name, string $type = 'App\Scoped\Paid'): Sharp\Listening
{
    return Sharp\Internal\listen($type, function (object $paid) use ($name): void {
        App\Scoped\Notices::$log[] = "$name $paid->orderId";
    });
}

function pay(int $orderId): void
{
    Sharp\Internal\emit(new App\Scoped\Paid($orderId));
}

// Prints what the listeners heard while $run ran.
function heard(string $case, Closure $run): void
{
    App\Scoped\Notices::$log = [];
    try {
        $run();
    } catch (RuntimeException $exception) {
        App\Scoped\Notices::$log[] = "caught {$exception->getMessage()}";
    }
    echo $case, ': ', implode(', ', App\Scoped\Notices::$log), "\n";
}

function importing(): void
{
    $listening = listening('scoped');
    pay(1);
}

function failing(): void
{
    $listening = listening('failing');
    throw new RuntimeException('boom');
}

// Each case runs twice, so the JIT variants compile the code before the second run.
for ($run = 0; $run < 2; $run++) {
    heard('a handle in a local, until its function returns', function (): void {
        importing();
        pay(2);
    });
    heard('until an exception leaves its function', function (): void {
        try {
            failing();
        } finally {
            pay(3);
        }
    });
    heard('a handle never stored', function (): void {
        Sharp\Internal\listen('App\Scoped\Paid', fn (object $paid) => App\Scoped\Notices::$log[] = 'dropped');
        pay(4);
    });
    heard('after the table, in the order they started, and through an interface', function (): void {
        $first = listening('first');
        $second = listening('second', 'App\Scoped\OrderEvent');
        pay(5);
    });
    heard('one started during an emit hears only the next', function (): void {
        $late = null;
        $starter = Sharp\Internal\listen('App\Scoped\Paid', function (object $paid) use (&$late): void {
            App\Scoped\Notices::$log[] = "starter $paid->orderId";
            $late ??= listening('late');
        });
        pay(6);
        pay(7);
    });
    heard('one whose handle is gone runs nothing under a dispatcher that runs it later', function (): void {
        $later = listening('later');
        (new App\Scoped\Later\Payer())->pay(8);
        $later = null;
        App\Scoped\Later\Later::flush();
    });
    heard('a type no class has', function (): void {
        $ghost = listening('ghost', 'App\Scoped\Nothing');
        pay(9);
    });
}
