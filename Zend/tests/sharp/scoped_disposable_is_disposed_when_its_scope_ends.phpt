--TEST--
The container disposes each scoped service that is Disposable when its scope ends, newest first: when Scope.run returns, when its body throws, and when the request's own scope ends, before each Disposable singleton, spec section 32
--FILE--
<?php
require __DIR__ . '/Container.inc';

container_project('scoped-disposable', [
    'app/Ledger/Module.sharp' => <<<'SHARP'
namespace App.Ledger;

module
{
    singleton Journal = Journal;
    singleton Archive = Archive;
    scoped Transaction = Transaction;
    scoped Lock = Lock;
    scoped Bell = Bell;
}
SHARP,
    'app/Ledger/Journal.sharp' => <<<'SHARP'
namespace App.Ledger;

public class Journal
{
    private string text = "";

    public void note(string line)
    {
        this.text = this.text == "" ? line : `${this.text}, ${line}`;
    }

    public string written() => this.text;
}
SHARP,
    'app/Ledger/Transaction.sharp' => <<<'SHARP'
namespace App.Ledger;

public class Transaction : Disposable
{
    public Transaction(private Journal journal)
    {
        journal.note("begin");
    }

    public void dispose()
    {
        this.journal.note("end");
    }
}
SHARP,
    'app/Ledger/Lock.sharp' => <<<'SHARP'
namespace App.Ledger;

public class Lock : Disposable
{
    public Lock(private Journal journal, Transaction transaction)
    {
        journal.note("lock");
    }

    public void dispose()
    {
        this.journal.note("unlock");
    }
}
SHARP,
    'app/Ledger/Bell.sharp' => <<<'SHARP'
namespace App.Ledger;

import Sharp.IO.Console;

public class Bell : Disposable
{
    public Bell(private Console console) { }

    public void dispose()
    {
        this.console.writeLine("the request's scope disposed its Bell");
    }
}
SHARP,
    'app/Ledger/Archive.sharp' => <<<'SHARP'
namespace App.Ledger;

import Sharp.IO.Console;

public class Archive : Disposable
{
    public Archive(private Console console) { }

    public void dispose()
    {
        this.console.writeLine("the request disposed its Archive singleton");
    }
}
SHARP,
]);
$container = new Sharp\Container();
$journal = $container->get('App\Ledger\Journal');

Sharp\Scope::run(function () use ($container, $journal) {
    $container->get('App\Ledger\Lock');
    $journal->note('body');
});
echo 'returned: ', $journal->written(), "\n";

try {
    Sharp\Scope::run(function () use ($container) {
        $container->get('App\Ledger\Lock');
        throw new RuntimeException('failed');
    });
} catch (RuntimeException $e) {
    $journal->note('caught ' . $e->getMessage());
}
echo 'thrown: ', $journal->written(), "\n";

$container->get('App\Ledger\Bell');
$container->get('App\Ledger\Archive');
echo "the script ends\n";
?>
--EXPECT--
returned: begin, lock, body, unlock, end
thrown: begin, lock, body, unlock, end, begin, lock, unlock, end, caught failed
the script ends
the request's scope disposed its Bell
the request disposed its Archive singleton
