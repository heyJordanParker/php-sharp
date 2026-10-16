--TEST--
PHP# yield produces an Iterable<T> lazily: yield value, yield ...other, a body that runs only when a loop reads it, and a generator that carries its element type, so `is Iterable<Row>` tests it without reading it
--FILE--
<?php

require __DIR__ . '/harness/Arrays.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('Import');

$path = tempnam(sys_get_temp_dir(), 'sharp_yield');
file_put_contents($path, "A1,tea,3\n\nA2,cake,5\n");

foreach (Demo\Import::rows($path) as $row) {
    echo implode('|', $row), "\n";
}
echo Demo\Import::fields(Demo\Import::rows($path)), "\n";

$import = new Demo\Import([new Demo\Row('P1'), new Demo\Row('P2')], new Demo\Row('L1'));
foreach ($import->all() as $row) {
    echo $row->number, "\n";
}
echo Demo\Import::last($import->all()), "\n";
echo $import->lastPending(), "\n";
echo Demo\Import::last([new Demo\Row('X1')]), "\n";
// `yield ...other` takes the generator's next automatic keys, so a plain PHP reader that keeps keys keeps every element.
echo implode(',', array_map(fn ($row) => $row->number, iterator_to_array($import->all()))), "\n";
echo implode(',', array_keys(iterator_to_array($import->all()))), "\n";

$missing = Demo\Import::rows(__DIR__ . '/missing.csv');
echo get_class($missing), "\n";
try {
    Demo\Import::fields($missing);
} catch (RuntimeException $e) {
    echo get_class($e), ': ', $e->getMessage(), "\n";
}

// A PHP# generator carries the type it yields, so the test reads none of it, and a plain PHP one holds any value.
$all = $import->all();
var_dump(Demo\Import::holdsRows($all), Demo\Import::holdsTexts($all), Demo\Import::holdsRows(Demo\Import::rows($path)));
echo $all->current()->number, "\n";
var_dump(Demo\Import::holdsRows((function () { yield new Demo\Row('G1'); })()));
// A generic method's generator yields the type its call gave it, and a call from plain PHP gives none, so `T` is `Any?`.
$repeated = Demo\Import::repeatRow(new Demo\Row('R1'));
var_dump(Demo\Import::holdsRows($repeated), Demo\Import::holdsTexts($repeated), Demo\Import::holdsRows(Demo\Import::repeat(new Demo\Row('R2'))));
foreach ($repeated as $row) {
    echo $row->number, "\n";
}
// A finished generator's frame is gone, so it carries no type.
var_dump(Demo\Import::holdsRows($repeated));
// A generator whose body calls plain PHP carries the type it yields, and so does a map stage whose lambda calls plain
// PHP, though neither has a function type, whose effects plain PHP leaves unknown. The test reads neither.
$printed = $import->printed();
var_dump(Demo\Import::holdsRows($printed), Demo\Import::holdsTexts($printed), Demo\Import::holdsInts($import->lengths()));
// An extension method yields as a method of its class does, and its generator carries the type it yields.
echo implode(',', Demo\Import::spelled('ab')), "\n";
var_dump(Demo\Import::holdsTexts(Demo\Words::letters('ab')), Demo\Import::holdsInts(Demo\Words::letters('ab')));

unlink($path);
?>
--EXPECTF--
A1|tea|3
A2|cake|5
6
P1
P2
L1
L1
P2
X1
P1,P2,L1
0,1,2
Generator
RuntimeException: SplFileObject::__construct(%smissing.csv): Failed to open stream: No such file or directory
bool(true)
bool(false)
bool(false)
P1
bool(false)
bool(true)
bool(false)
bool(false)
R1
R1
bool(false)
bool(true)
bool(false)
bool(true)
ab,AB
bool(true)
bool(false)
