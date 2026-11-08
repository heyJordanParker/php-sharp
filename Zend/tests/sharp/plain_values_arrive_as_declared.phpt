--TEST--
A plain PHP value arrives in PHP# as its declared type says wherever PHP# holds it: a bare array is a Map, read straight or through a local, whose values() is a List, and a list its PHPDoc declares is a List, and a plain PHP override of a PHP# method, a generated test double's included, gives its PHP array as the Map the method declares where the call is written, as an argument, a constructor's argument, a literal's element, a spread, a static call through Self or a class value, and a null-safe call, and a plain PHP interface's array spreads into a Map and a List literal
--FILE--
<?php
require __DIR__ . '/harness/Folder.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('Arrivals');
require __DIR__ . '/harness/MovedSource.inc';
require __DIR__ . '/harness/Feed.inc';

$folder = new Lib\Folder();
$folder->records = [['type' => 'TXT', 'name' => 'x']];
$folder->listed = [['type' => 'TXT', 'name' => 'x']];
$reader = new Arrivals\Reader();
var_dump($reader->records($folder), $reader->heldRecords($folder), $reader->heldRecordsAfterNull($folder));
var_dump($reader->recordsMap($folder));
var_dump($reader->listed($folder), $reader->heldListed($folder));

// A double PHPUnit's createMock or Mockery generates while the tests run is a plain PHP subclass made by eval.
eval('namespace Lib; class SourceDouble extends \Arrivals\Source { public function status(): array { return ["status" => "doubled"]; } }');
foreach ([new Arrivals\Source(), new Lib\MovedSource(), new Lib\SourceDouble()] as $source) {
    echo $reader->status($source), ' ', $reader->heldStatus($source), ' ', $reader->typedStatus($source), "\n";
    // The call crosses where it is written: as an argument, a constructor's argument, a literal's element, a spread,
    // and a static method `Self.` or a class value reaches on the class the object is of.
    echo $reader->argument($source), ' ', $reader->constructed($source), ' ', $reader->element($source), ' ',
        $reader->spread($source), ' ', $reader->late($source), ' ', $reader->ofClass($source), "\n";
}
$feed = new Lib\ArrayFeed();
var_dump($reader->fed($feed), $reader->named($feed));
// A null-safe call on null gives null, and on an override the `Map` its array crosses into.
var_dump($reader->maybe(null), $reader->maybe(new Lib\MovedSource()));
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
active active active
active active active active made made
moved moved moved
moved moved moved moved remade remade
doubled doubled doubled
doubled doubled doubled doubled made made
string(3) "fed"
int(3)
NULL
string(5) "moved"
