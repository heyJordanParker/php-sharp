--TEST--
PHP# yield produces an Iterable<T> lazily: yield value, yield ...other, and a body that runs only when a loop reads it
--FILE--
<?php

require __DIR__ . '/Import.sharp';

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

$missing = Demo\Import::rows(__DIR__ . '/missing.csv');
echo get_class($missing), "\n";
try {
    Demo\Import::fields($missing);
} catch (RuntimeException $e) {
    echo get_class($e), ': ', $e->getMessage(), "\n";
}

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
Generator
RuntimeException: SplFileObject::__construct(%smissing.csv): Failed to open stream: No such file or directory
