--TEST--
Database.transaction commits a body that returns and rolls back one that throws, runs the listeners of each emit in it once it commits and never after a rollback, runs a body a conflict refused again up to five times with only the last run's emits heard, and runs a transaction inside a body in the open one, for the standard library's dispatcher and for one a test module binds
--EXTENSIONS--
pdo_sqlite
--FILE--
<?php
require __DIR__ . '/Container.inc';

$root = container_project('database-transaction', [
    'app/Shop/Reserved.sharp' => <<<'SHARP'
        namespace App.Shop;

        public event Reserved(string sku);
        SHARP,
    'app/Shop/Hears.sharp' => <<<'SHARP'
        namespace App.Shop;

        public class Hears
        {
            public static List<string> log { get; set; } = [];

            public static void hear(string line) => Hears.log.add(line);

            public void reserved(Reserved e) on Reserved
            {
                Hears.hear(`heard ${e.sku}`);
            }
        }
        SHARP,
    'app/Shop/Stock.sharp' => <<<'SHARP'
        namespace App.Shop;

        import RuntimeException;
        import Sharp.Data.Database;

        public class Stock
        {
            public Stock(private Database database) { }

            public int reserve(string sku, bool fails)
                => this.database.transaction(() => {
                    const changed = this.database.execute("UPDATE stock SET count = count - 1 WHERE sku = ?", [sku]);
                    emit new Reserved(sku);
                    Hears.log.add(`body ${sku}`);
                    if (fails) {
                        throw new RuntimeException(`no courier for ${sku}`);
                    }

                    return changed;
                });

            public int reserveTwice(string sku)
                => this.database.transaction(() => this.reserve(sku, false) + this.reserve(sku, false));
        }
        SHARP,
    'app/Testing/Recorder.sharp' => <<<'SHARP'
        namespace App.Testing;

        import App.Shop.Hears;
        import App.Shop.Reserved;
        import Sharp.Dispatcher;

        public class Recorder : Dispatcher
        {
            public void dispatch(Any emitted, List<Function<void(Any)>> listeners)
            {
                Hears.hear(emitted is Reserved reserved ? `dispatched ${reserved.sku}` : "dispatched another event");
            }
        }
        SHARP,
]);

// A refusal Postgres gives a transaction another one changed what it read.
final class Conflict extends PDOException
{
    protected $code = '40001';
}

$database = new Sharp\Data\SystemDatabase('sqlite::memory:');
$database->execute('CREATE TABLE stock (sku TEXT PRIMARY KEY, count INTEGER)');
$database->execute("INSERT INTO stock (sku, count) VALUES ('tea', 10)");
$stock = new App\Shop\Stock($database);
$count = fn (): int => $database->scalar("SELECT count FROM stock WHERE sku = 'tea'");

function heard(string $case, Closure $run): void
{
    App\Shop\Hears::$log = [];
    try {
        $result = $run();
    } catch (Throwable $e) {
        $result = $e::class . ': ' . $e->getMessage();
    }
    echo $case, ': ', $result, '; ', implode(', ', App\Shop\Hears::$log) ?: 'nothing heard', "\n";
}

heard('a body that returns', fn () => $stock->reserve('tea', false));
echo 'count: ', $count(), "\n";
heard('a body that throws', fn () => $stock->reserve('tea', true));
echo 'count: ', $count(), "\n";
heard('a transaction inside a body', fn () => $stock->reserveTwice('tea'));
echo 'count: ', $count(), "\n";

$tries = 0;
heard('a body a conflict refused once', fn () => $database->transaction(function () use (&$tries, $database): int {
    $tries++;
    $database->execute("UPDATE stock SET count = count - 1 WHERE sku = 'tea'");
    Sharp\Internal\emit(new App\Shop\Reserved("try $tries"), 'Sharp\Dispatcher');
    if ($tries === 1) {
        throw new Conflict('could not serialize access due to read/write dependencies among transactions');
    }

    return $tries;
}));
echo 'count: ', $count(), "\n";

$tries = 0;
heard('a body every run conflicts', function () use (&$tries, $database): int {
    return $database->transaction(function () use (&$tries): int {
        $tries++;
        Sharp\Internal\emit(new App\Shop\Reserved("try $tries"), 'Sharp\Dispatcher');
        throw new Conflict('could not serialize access due to concurrent update');
    });
});
echo 'runs: ', $tries, ', previous: ', get_class((function () use ($database) {
    try {
        $database->transaction(fn () => throw new Conflict('again'));
    } catch (RuntimeException $e) {
        return $e->getPrevious();
    }
})()), "\n";

heard('an emit outside a transaction', fn () => Sharp\Internal\emit(new App\Shop\Reserved('now'), 'Sharp\Dispatcher'));

Sharp\Internal\testModule('Tests\RecorderTest', [['app', 'Sharp\Dispatcher', null, 'singleton', 'App\Testing\Recorder']]);
Sharp\Internal\startTest('Tests\RecorderTest');
heard("the app's dispatcher, a body that returns", fn () => $stock->reserve('tea', false));
heard("the app's dispatcher, a body that throws", fn () => $stock->reserve('tea', true));
Sharp\Internal\endTest();
echo 'count: ', $count(), "\n";
remove_project($root);
?>
--EXPECT--
a body that returns: 1; body tea, heard tea
count: 9
a body that throws: RuntimeException: no courier for tea; body tea
count: 9
a transaction inside a body: 2; body tea, body tea, heard tea, heard tea
count: 7
a body a conflict refused once: 2; heard try 2
count: 6
a body every run conflicts: RuntimeException: Database.transaction: the transaction conflicted with another 5 times, so it was not run again; nothing heard
runs: 5, previous: Conflict
an emit outside a transaction: ; heard now
the app's dispatcher, a body that returns: 1; body tea, dispatched tea
the app's dispatcher, a body that throws: RuntimeException: no courier for tea; body tea
count: 5
