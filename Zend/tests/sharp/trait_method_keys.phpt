--TEST--
A class keys each method a trait gives it by its own rule, never the trait's: a plain PHP class overrides a PHP# interface's camelCase default body and a PHP# class a plain trait's camelCase method with one method each, `as` and `insteadof` across a PHP# and a plain trait name each method once, and a default body a plain PHP class takes is found by any call
--FILE--
<?php

require __DIR__ . '/harness/TraitKeys.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('TraitKeys');

$names = static fn (object $object): array => array_map(
    static fn (ReflectionMethod $method): string => $method->name,
    (new ReflectionClass($object))->getMethods(),
);

// A plain PHP class's own `recordEntry` and `count` override the PHP# interfaces' default bodies.
final class Ledger implements Probe\Recorded, Probe\Tally
{
    use Probe\Recorded\Defaults;
    use Probe\Tally\Defaults;

    public array $entries = [];

    public function recordEntry(int $id): void
    {
        $this->entries[] = $id;
    }

    public function count(): int
    {
        return 1;
    }
}

$ledger = new Ledger();
$ledger->recordEntry(7);
var_dump($ledger->entries, $ledger->count(), $names($ledger));
var_dump((new ReflectionMethod($ledger, 'recordEntry'))->getDeclaringClass()->name);

// A PHP# class's own `stockLabel` overrides a plain trait's.
$shelf = new Probe\Shelf();
var_dump($shelf->stockLabel(), $names($shelf));

// `insteadof` and `as` name a PHP# trait's camelCase method and a plain trait's, each way round.
final class Journal
{
    use Lib\Recording, Probe\Recorded\Defaults {
        Lib\Recording::recordEntry insteadof Probe\Recorded\Defaults;
        Probe\Recorded\Defaults::recordEntry as sharpEntry;
    }
}

final class Diary
{
    use Lib\Recording, Probe\Recorded\Defaults {
        Probe\Recorded\Defaults::recordEntry insteadof Lib\Recording;
        Lib\Recording::recordEntry as plainEntry;
    }
}

$journal = new Journal();
$journal->recordEntry(1);
$journal->sharpEntry(2);
var_dump($journal->entries, $names($journal));
$diary = new Diary();
$diary->recordEntry(3);
$diary->plainEntry(4);
var_dump($diary->entries, $names($diary));

// A plain PHP class that takes a camelCase default body without overriding it runs it by any call.
final class Keeper implements Probe\Recorded
{
    use Probe\Recorded\Defaults;
}

$keeper = new Keeper();
$keeper->recordEntry(5);
var_dump(method_exists($keeper, 'recordEntry'), is_callable([$keeper, 'recordEntry']), $names($keeper));
?>
--EXPECT--
array(1) {
  [0]=>
  int(7)
}
int(1)
array(2) {
  [0]=>
  string(11) "recordEntry"
  [1]=>
  string(5) "count"
}
string(6) "Ledger"
string(11) "sharp shelf"
array(3) {
  [0]=>
  string(10) "stockLabel"
  [1]=>
  string(5) "parse"
  [2]=>
  string(8) "tryParse"
}
array(1) {
  [0]=>
  string(7) "plain 1"
}
array(2) {
  [0]=>
  string(11) "recordEntry"
  [1]=>
  string(10) "sharpEntry"
}
array(1) {
  [0]=>
  string(7) "plain 4"
}
array(2) {
  [0]=>
  string(10) "plainEntry"
  [1]=>
  string(11) "recordEntry"
}
bool(true)
bool(true)
array(1) {
  [0]=>
  string(11) "recordEntry"
}
