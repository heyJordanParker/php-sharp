--TEST--
A List<int> property a `ref` member changed stays checked: PHP# reads what plain PHP wrote into it after the change, or through a reference plain PHP took by iterating the object while the change ran, and throws TypeError at the bad element
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
$ledger->forget();
try {
    $ledger->remembered();
} catch (TypeError $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
6
TypeError: Reference held by property ListChanges\Ledger::$counts of type List<int> holds array: [3] expected int, got string 'z'
TypeError: Reference held by property ListChanges\Ledger::$callbacks of type List<Function<void()>> holds array: [0] expected Function<void()>, got string 'bad'
