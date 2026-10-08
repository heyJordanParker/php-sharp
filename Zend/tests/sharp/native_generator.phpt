--TEST--
sharp/bin/native writes the same native interface on every run, and an edited Rust source, a native dependency's version or a native git dependency's rev changes SHARP_NATIVE
--FILE--
<?php
require __DIR__ . '/native_generator.inc';

$copy = sys_get_temp_dir() . '/sharp_native_generator';
native_generator_remove($copy);
native_generator_copy($copy);
$header = "$copy/ext/sharp/sharp_native.h";
$library = "$copy/sharp/composer/native.php";

$status = native_generator_run($copy);
echo 'first run exits with ', $status, "\n";
$first = [file_get_contents($header), file_get_contents($library)];
$status = native_generator_run($copy);
echo 'second run exits with ', $status, "\n";
echo [file_get_contents($header), file_get_contents($library)] === $first ? 'same' : 'different', " files on the second run\n";

preg_match('/^#define SHARP_NATIVE "([0-9a-f]{32})"$/m', $first[0], $define);
$fingerprint = native_generator_fingerprint($copy);
echo $define[1] === $fingerprint ? 'one' : 'two', " SHARP_NATIVE in sharp_native.h and native.php\n";
preg_match('/^static const char \*const sharp_native_bodies\[\] = \{\n((?:\t"[^"\n]+",\n)*)\tNULL\n\};$/m', $first[0], $table);
preg_match_all('/"([^"]+)"/', $table[1], $engine);
preg_match("/^    'bodies' => \[\n((?:        '[^'\n]+',\n)*)    \],$/m", $first[1], $list);
preg_match_all("/'([^']+)'/", $list[1], $declared);
echo $engine[1] !== [] && $engine[1] === $declared[1] ? 'one' : 'two', " list of native bodies in sharp_native.h and native.php\n";

file_put_contents("$copy/ext/sharp/src/lib.rs", "\n", FILE_APPEND);
$status = native_generator_run($copy);
echo 'run after the edit exits with ', $status, "\n";
echo native_generator_fingerprint($copy) === $fingerprint ? 'same' : 'new', " SHARP_NATIVE after a Rust source changed\n";

$fingerprints = [];
foreach ([
    'the fixture lock' => ['1.6.2', '5e2a71c0d84b9f3a6c18e0f27d93b4a85c6e1d07'],
    'deunicode moved to 1.6.1' => ['1.6.1', '5e2a71c0d84b9f3a6c18e0f27d93b4a85c6e1d07'],
    'the unicode-tables git rev moved' => ['1.6.2', 'a93f0d6e2b7c41858e0d3f6a1c2b9e74d05f8c63'],
] as $lock => [$deunicode, $tables]) {
    native_generator_lock($copy, $deunicode, $tables);
    $status = native_generator_run($copy);
    $fingerprints[$lock] = native_generator_fingerprint($copy);
    echo $status === 0 ? '' : "exits with $status: ", $fingerprints[$lock] === reset($fingerprints) ? 'same' : 'new', " SHARP_NATIVE with $lock\n";
}
native_generator_remove($copy);
?>
--EXPECT--
first run exits with 0
second run exits with 0
same files on the second run
one SHARP_NATIVE in sharp_native.h and native.php
one list of native bodies in sharp_native.h and native.php
run after the edit exits with 0
new SHARP_NATIVE after a Rust source changed
same SHARP_NATIVE with the fixture lock
new SHARP_NATIVE with deunicode moved to 1.6.1
new SHARP_NATIVE with the unicode-tables git rev moved
