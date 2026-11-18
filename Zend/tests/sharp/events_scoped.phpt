--TEST--
A listener that PHP#'s Events.on or plain PHP's Sharp\Internal\listen starts hears each event after the table's listeners, in the order the listeners started, until its handle goes out of scope
--FILE--
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
    'app/Scoped/Screen.sharp' => <<<'SHARP'
        namespace App.Scoped;

        public class Screen
        {
            public void watch(int orderId)
            {
                const listening = Events.on<Paid>(e => Notices.log.add(`screen ${e.orderId}`));
                emit new Paid(orderId);
            }

            public void watchEvery(int orderId)
            {
                const listening = Events.on<OrderEvent>(e => Notices.log.add(`any ${e.orderId}`));
                emit new Paid(orderId);
            }
        }
        SHARP,
    'app/Scoped/Later.sharp' => <<<'SHARP'
        namespace App.Scoped;

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
]);
Sharp\Internal\testModule('Tests\LaterTest', [['app', 'Sharp\Dispatcher', null, 'singleton', 'App\Scoped\Later']]);

// Keeps its listener's handle in a property, and its listener uses it, so the two only go together.
final class Keeper
{
    private Sharp\Listening $listening;

    public function __construct()
    {
        $this->listening = Sharp\Internal\listen('App\Scoped\Paid', function (object $paid): void {
            $this->hear($paid);
        });
    }

    private function hear(object $paid): void
    {
        App\Scoped\Notices::$log[] = "kept $paid->orderId";
    }

    public function __destruct()
    {
        App\Scoped\Notices::$log[] = 'keeper destroyed';
    }
}

// Starts a listener that logs its name and the order, on Paid or the type named.
function listening(string $name, string $type = 'App\Scoped\Paid'): Sharp\Listening
{
    return Sharp\Internal\listen($type, function (object $paid) use ($name): void {
        App\Scoped\Notices::$log[] = "$name $paid->orderId";
    });
}

function pay(int $orderId): void
{
    Sharp\Internal\emit(new App\Scoped\Paid($orderId), 'Sharp\Dispatcher');
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
        Sharp\Internal\startTest('Tests\LaterTest');
        $later = listening('later');
        pay(8);
        $later = null;
        App\Scoped\Later::flush();
        Sharp\Internal\endTest();
    });
    heard('a type no class has', function (): void {
        $ghost = listening('ghost', 'App\Scoped\Nothing');
        pay(9);
    });
    heard('Events.on in PHP#, until its method returns', function (): void {
        (new App\Scoped\Screen())->watch(10);
        pay(11);
    });
    heard('Events.on through an interface', function (): void {
        (new App\Scoped\Screen())->watchEvery(12);
        pay(13);
    });
    heard('a handle in a property of the object its listener uses, until the collector collects both', function (): void {
        $keeper = new Keeper();
        pay(14);
        $keeper = null;
        gc_collect_cycles();
        pay(15);
    });
}
?>
--EXPECT--
a handle in a local, until its function returns: table 1, scoped 1, table 2
until an exception leaves its function: table 3, caught boom
a handle never stored: table 4
after the table, in the order they started, and through an interface: table 5, first 5, second 5
one started during an emit hears only the next: table 6, starter 6, table 7, starter 7, late 7
one whose handle is gone runs nothing under a dispatcher that runs it later: table 8
a type no class has: table 9
Events.on in PHP#, until its method returns: table 10, screen 10, table 11
Events.on through an interface: table 12, any 12, table 13
a handle in a property of the object its listener uses, until the collector collects both: table 14, kept 14, keeper destroyed, table 15
a handle in a local, until its function returns: table 1, scoped 1, table 2
until an exception leaves its function: table 3, caught boom
a handle never stored: table 4
after the table, in the order they started, and through an interface: table 5, first 5, second 5
one started during an emit hears only the next: table 6, starter 6, table 7, starter 7, late 7
one whose handle is gone runs nothing under a dispatcher that runs it later: table 8
a type no class has: table 9
Events.on in PHP#, until its method returns: table 10, screen 10, table 11
Events.on through an interface: table 12, any 12, table 13
a handle in a property of the object its listener uses, until the collector collects both: table 14, kept 14, keeper destroyed, table 15
