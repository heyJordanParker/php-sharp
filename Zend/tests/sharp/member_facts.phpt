--TEST--
Class<T>.methods() and properties() give each member's declaring class and visibility, a method's `isStatic`, and a parameter's `isVariadic` and default value, for a PHP# class and a plain PHP class, inherited members included, and plain PHP still reaches an `internal` member as public
--FILE--
<?php
require __DIR__ . '/harness/MemberFacts.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('MemberFacts');

foreach ([App\Facts\Facts::journal(), App\Facts\Facts::tag(), App\Facts\Facts::plainJournal()] as $lines) {
    echo implode("\n", $lines), "\n";
}

var_dump(App\Facts\Facts::readsANewDefaultEachTime());try {
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
property App.Facts.Ledger.level Protected
property App.Facts.Ledger.count Private
method App.Facts.Journal.title() Public
method App.Facts.Ledger.open() Public static
method App.Facts.Ledger.total(limit = 2, tag = new Tag(first), start = 3, ...notes) Internal
method App.Facts.Ledger.reset(depth) Protected
property App.Facts.Tag.label Internal
property Lib.PlainLedger.code Public
property Lib.PlainLedger.level Protected
property Lib.PlainLedger.count Private
method Lib.PlainJournal.title() Public
method Lib.PlainLedger.open() Public static
method Lib.PlainLedger.reset(depth) Protected
method Lib.PlainLedger.total(limit = 2, tag = new Tag(plain), ...notes) Private
bool(true)
`defaultValue` reads a parameter that has a default, and `depth` of `reset` has none.
bool(true)
bool(true)
array(1) {
  [0]=>
  string(23) "Sharp\Internal\Internal"
}
bool(true)
