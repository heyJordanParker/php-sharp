--TEST--
A plain PHP value arrives in PHP# as its declared type says wherever PHP# holds it: a bare array is a Map, read straight or through a local, and a list its PHPDoc declares is a List, and a plain PHP override of a PHP# method, a generated test double's included, gives its PHP array as the Map the method declares
--FILE--
<?php
require __DIR__ . '/harness/Folder.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('Arrivals');
require __DIR__ . '/harness/MovedSource.inc';

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
}
?>
--EXPECT--
bool(false)
bool(false)
bool(false)
bool(true)
bool(true)
bool(true)
active active active
moved moved moved
doubled doubled doubled
