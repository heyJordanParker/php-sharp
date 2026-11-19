--TEST--
Class<T>.methods(), properties() and fields() give each member's declaring class and visibility, a method's `isStatic`, and a parameter's `isVariadic`, `hasConstantDefault` and a default that runs no code, refusing one that runs code with no object created, for a PHP# class and a plain PHP class, inherited members included, fields() gives each of a plain PHP class's properties that declares no hook at every visibility, a Field refuses a receiver whose class declares its property again with hooks and runs neither, and plain PHP still reaches an `internal` member as public
--FILE--
<?php
require __DIR__ . '/harness/MemberFacts.inc';
require __DIR__ . '/vendor/autoload.php';

foreach ([App\Facts\Facts::journal(), App\Facts\Facts::tag(), App\Facts\Facts::plainJournal()] as $lines) {
    echo implode("\n", $lines), "\n";
}

var_dump(App\Facts\Facts::hookedRefusals());
var_dump(App\Facts\Facts::refusedDefaults());try {
    App\Facts\Facts::required()->defaultValue();
} catch (OutOfRangeException $error) {
    echo $error->getMessage(), "\n";
}

// `internal` is a rule of the checker, so plain PHP reaches the member as public, and reads its mark.
$total = new ReflectionMethod(App\Facts\Ledger::class, 'total');
var_dump($total->isPublic(), (new ReflectionProperty(App\Facts\Ledger::class, 'code'))->isPublic());
var_dump(array_map(static fn (ReflectionAttribute $attribute): string => $attribute->getName(), $total->getAttributes()));
var_dump($total->getAttributes()[0]->newInstance() instanceof Sharp\Internal\Internal);
?>
--EXPECT--
property App.Facts.Ledger.code Internal
field App.Facts.Ledger.code Internal
field App.Facts.Ledger.level Protected
field App.Facts.Ledger.count Private
method App.Facts.Journal.title() Public
method App.Facts.Ledger.open() Public static
method App.Facts.Ledger.total(limit = 2, tag runs code, start runs code, ...notes) Internal
method App.Facts.Ledger.reset(depth) Protected
property App.Facts.Tag.made Public
property App.Facts.Tag.label Internal
field App.Facts.Tag.made Public
property Lib.PlainLedger.code Public
property Lib.PlainLedger.level Protected
property Lib.PlainLedger.count Private
field Lib.PlainLedger.code Public
field Lib.PlainLedger.level Protected
field Lib.PlainLedger.count Private
method Lib.PlainJournal.title() Public
method Lib.PlainLedger.open() Public static
method Lib.PlainLedger.reset(depth) Protected
method Lib.PlainLedger.total(limit = 2, tag runs code, ...notes) Private
array(2) {
  [0]=>
  string(123) "Error: `get` reads the storage of Lib\PlainLedger::$code, and Lib\HookedLedger gives it an accessor, which `get` never runs"
  [1]=>
  string(124) "Error: `set` writes the storage of Lib\PlainLedger::$code, and Lib\HookedLedger gives it an accessor, which `set` never runs"
}
array(3) {
  [0]=>
  string(126) "`defaultValue` reads a default that runs no code, and `tag` of `total` has one that runs code, which `defaultValue` never runs"
  [1]=>
  string(126) "`defaultValue` reads a default that runs no code, and `tag` of `total` has one that runs code, which `defaultValue` never runs"
  [2]=>
  int(0)
}
`defaultValue` reads a parameter that has a default, and `depth` of `reset` has none.
bool(true)
bool(true)
array(1) {
  [0]=>
  string(23) "Sharp\Internal\Internal"
}
bool(true)
