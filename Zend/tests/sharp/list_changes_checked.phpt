--TEST--
A List<int> property a `ref` member changed stays checked: PHP# reads what plain PHP wrote into it, or into an element a change went through, after the change, and what plain PHP wrote while the change ran through a reference it took by iterating the object, a lazy proxy of it or an ArrayIterator over it, or through a copy of the property whose element the change held, and throws TypeError at the bad element
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('ListChanges');

use ListChanges\Ledger;

$ledger = new Ledger();
for ($count = 1; $count <= 3; $count++) {
    $ledger->count($count);
}
echo $ledger->total(), "\n";

$ledger->counts[] = 'z';
try {
    $ledger->total();
} catch (TypeError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}

// The same for an element a change went through, which plain PHP writes once the change returned.
$ledger->addToRow(1);
$ledger->rows[0][] = 'z';
try {
    $ledger->rowTotal();
} catch (TypeError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}

// A destructor `forget` runs while it holds the property by reference iterates the ledger by reference, and writes
// through the reference it takes.
final class Spy
{
    public function __construct(private Ledger $ledger) {}

    public function __destruct()
    {
        foreach ($this->ledger as $name => &$value) {
            if ($name === 'callbacks') {
                $value[] = 'bad';
            }
        }
    }
}

$ledger = new Ledger();
$spy = new Spy($ledger);
$ledger->callbacks = [static function () use ($spy): void {}];
unset($spy);
try {
    $ledger->forget();
    echo $ledger->remembered(), "\n";
} catch (TypeError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}

// A destructor `forgetHooks` runs while it holds an element of `hooks` by reference copies `hooks`, and writes the
// copy's element, which shares the element's reference with the property.
final class Copier
{
    public function __construct(private Ledger $ledger) {}

    public function __destruct()
    {
        $copy = $this->ledger->hooks;
        $copy[0][] = 'bad';
    }
}

// A destructor `forget` runs writes through the reference an ArrayIterator over the ledger reaches, and through the
// reference a loop by reference over a lazy proxy of the ledger takes.
final class Iterated
{
    public function __construct(private object $ledger) {}

    public function __destruct()
    {
        $iterator = @new ArrayIterator($this->ledger);
        $iterator['callbacks'][] = 'bad';
    }
}

final class Proxied
{
    public function __construct(private object $proxy) {}

    public function __destruct()
    {
        foreach ($this->proxy as $name => &$value) {
            if ($name === 'callbacks') {
                $value[] = 'bad';
            }
        }
    }
}

foreach ([Iterated::class, Proxied::class] as $writes) {
    $ledger = new Ledger();
    $class = new ReflectionClass(Ledger::class);
    $proxy = $class->newLazyProxy(fn () => $ledger);
    $class->initializeLazyObject($proxy);
    $writer = new $writes($writes === Iterated::class ? $ledger : $proxy);
    $ledger->callbacks = [static function () use ($writer): void {}];
    unset($writer, $proxy);
    try {
        $ledger->forget();
        echo $ledger->remembered(), "\n";
    } catch (TypeError $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}

$ledger = new Ledger();
$copier = new Copier($ledger);
$ledger->hooks = [[static function () use ($copier): void {}]];
unset($copier);
try {
    $ledger->forgetHooks();
    echo $ledger->hooked(), "\n";
} catch (TypeError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
6
TypeError: Reference held by property ListChanges\Ledger::$counts of type List<int> holds array: [3] expected int, got string 'z'
TypeError: Reference held by property ListChanges\Ledger::$rows of type List<List<int>> holds array: [0][1] expected int, got string 'z'
TypeError: Reference held by property ListChanges\Ledger::$callbacks of type List<Function<void()>> holds array: [0] expected Function<void()>, got string 'bad'
TypeError: Reference held by property ListChanges\Ledger::$callbacks of type List<Function<void()>> holds array: [0] expected Function<void()>, got string 'bad'
TypeError: Reference held by property ListChanges\Ledger::$callbacks of type List<Function<void()>> holds array: [0] expected Function<void()>, got string 'bad'
TypeError: Reference held by property ListChanges\Ledger::$hooks of type List<List<Function<void()>>> holds array: [0][0] expected Function<void()>, got string 'bad'
